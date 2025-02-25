<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Service;

use Dbp\Relay\AuthorizationBundle\API\ResourceActionGrantService;
use Dbp\Relay\CoreBundle\Doctrine\QueryHelper;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\Helpers\Tools;
use Dbp\Relay\CoreBundle\Rest\Query\Filter\FilterTreeBuilder;
use Dbp\Relay\CoreBundle\Rest\Query\Pagination\Pagination;
use Dbp\Relay\FormalizeBundle\Authorization\AuthorizationService;
use Dbp\Relay\FormalizeBundle\Entity\Form;
use Dbp\Relay\FormalizeBundle\Entity\Submission;
use Dbp\Relay\FormalizeBundle\Event\CreateSubmissionPostEvent;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\ORM\QueryBuilder;
use JsonSchema\Constraints\Constraint;
use JsonSchema\Exception\InvalidSchemaException;
use JsonSchema\Exception\ValidationException;
use JsonSchema\Validator;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Scienta\DoctrineJsonFunctions\Query\AST\Functions\Mysql\JsonExtract;
use Scienta\DoctrineJsonFunctions\Query\AST\Functions\Mysql\JsonKeys;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

class FormalizeService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public const WHERE_READ_FORM_SUBMISSIONS_GRANTED_FILTER = 'whereReadFormSubmissionsGranted';
    public const WHERE_CONTAINS_SUBMISSIONS_MAY_READ_FILTER = 'whereContainsSubmissionsMayRead';
    public const OUTPUT_VALIDATION_FILTER = 'outputValidation';
    public const OUTPUT_VALIDATION_KEYS = 'KEYS';

    private const FORM_NOT_FOUND_ERROR_ID = 'formalize:form-with-id-not-found';
    private const REQUIRED_FIELD_MISSION_ID = 'formalize:required-field-missing';
    private const GETTING_FORM_ITEM_FAILED_ERROR_ID = 'formalize:getting-form-item-failed';
    private const GETTING_FORM_COLLECTION_FAILED_ERROR_ID = 'formalize:getting-form-collection-failed';
    private const GETTING_SUBMISSION_ITEM_FAILED_ERROR_ID = 'formalize:getting-submission-item-failed';
    private const GETTING_SUBMISSION_COLLECTION_FAILED_ERROR_ID = 'formalize:getting-submission-collection-failed';
    private const ADDING_FORM_FAILED_ERROR_ID = 'formalize:form-not-created';
    private const REMOVING_SUBMISSION_FAILED_ERROR_ID = 'formalize:submission-not-removed';
    private const REMOVING_FORM_SUBMISSIONS_FAILED = 'formalize:form-submissions-not-removed';
    private const ADDING_SUBMISSION_FAILED_ERROR_ID = 'formalize:submission-not-created';
    private const UPDATING_SUBMISSION_FAILED_ERROR_ID = 'formalize:submission-not-updated';
    private const SUBMISSION_NOT_FOUND_ERROR_ID = 'formalize:submission-not-found';
    private const REMOVING_FORM_FAILED_ERROR_ID = 'formalize:form-not-removed';
    private const UPDATING_FORM_FAILED_ERROR_ID = 'formalize:updating-form-failed';
    private const UNEXPECTED_ERROR_ID = 'formalize:unexpected-error';
    private const SUBMISSION_DATA_FEED_ELEMENT_INVALID_JSON_ERROR_ID = 'formalize:submission-invalid-json';
    public const SUBMISSION_DATA_FEED_ELEMENT_INVALID_SCHEMA_ERROR_ID = 'formalize:submission-data-feed-invalid-schema';
    private const FORM_INVALID_DATA_FEED_SCHEMA_ERROR_ID = 'formalize:form-invalid-data-feed-schema';
    private const SUBMISSION_FORM_CURRENTLY_NOT_AVAILABLE_ERROR_ID = 'formalize:submission-form-currently-not-available';
    private const SUBMISSION_STATE_NOT_ALLOWED_ERROR_ID = 'formalize:submission-state-not-allowed';

    private const SUBMISSION_ENTITY_ALIAS = 's';
    private const FORM_ENTITY_ALIAS = 'f';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AuthorizationService $authorizationService,
        private bool $debug = false)
    {
    }

    /**
     * @throws Exception
     */
    public function checkConnection(): void
    {
        $this->entityManager->getConnection()->getNativeConnection();
    }

    public function setDebug(bool $debug): void
    {
        $this->debug = $debug;
    }

    /**
     * @return Submission[]
     *
     * @throws ApiError
     */
    public function getSubmittedFormSubmissions(string $formIdentifier, array $filters = [],
        int $firstResultIndex = 0, int $maxNumResults = 30): array
    {
        try {
            return $this->createGetSubmissionsQueryBuilder(self::SUBMISSION_ENTITY_ALIAS,
                whereFormIdentifierEquals: $formIdentifier,
                whereSubmissionStateEquals: Submission::SUBMISSION_STATE_SUBMITTED,
                filters: $filters,
                firstResultIndex: $firstResultIndex,
                maxNumResults: $maxNumResults)
                ->getQuery()
                ->getResult();
        } catch (\Exception $exception) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getMessage(),
                self::GETTING_SUBMISSION_COLLECTION_FAILED_ERROR_ID, [$formIdentifier]);
        }
    }

    /**
     * @return string[]
     *
     * @throws ApiError
     */
    public function getSubmissionIdentifiersByForm(string $formIdentifier): array
    {
        try {
            $SUBMISSION_ENTITY_ALIAS = 's';
            $queryBuilder = $this->entityManager->createQueryBuilder();

            return $queryBuilder
                ->select("$SUBMISSION_ENTITY_ALIAS.identifier")
                ->from(Submission::class, $SUBMISSION_ENTITY_ALIAS)
                ->where($queryBuilder->expr()->eq("$SUBMISSION_ENTITY_ALIAS.form", ':formIdentifier'))
                ->setParameter(':formIdentifier', $formIdentifier)
                ->getQuery()
                ->getSingleColumnResult();
        } catch (\Exception $exception) {
            // TODO: error code
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getMessage(),
                self::GETTING_SUBMISSION_COLLECTION_FAILED_ERROR_ID);
        }
    }

    /**
     * @throws ApiError
     */
    public function getSubmissionByIdentifier(string $identifier): Submission
    {
        try {
            $submission = $this->entityManager
                ->getRepository(Submission::class)
                ->find($identifier);
        } catch (\Exception $exception) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getMessage(),
                self::GETTING_SUBMISSION_ITEM_FAILED_ERROR_ID);
        }

        if ($submission === null) {
            $apiError = ApiError::withDetails(Response::HTTP_NOT_FOUND, 'Submission was not found!',
                self::SUBMISSION_NOT_FOUND_ERROR_ID, [$identifier]);
            throw $apiError;
        }

        $submission->setGrantedActions(
            $this->authorizationService->getGrantedSubmissionItemActions($submission));

        return $submission;
    }

    /**
     * @throws ApiError
     */
    public function addSubmission(Submission $submission): Submission
    {
        $submission->setIdentifier((string) Uuid::v4());
        $submission->setDateCreated(new \DateTime('now'));
        $submission->setCreatorId($this->authorizationService->getUserIdentifier());

        $this->assertSubmissionIsValid($submission);

        $wasSubmissionAddedToAuthorization = false;
        try {
            if ($submission->getForm()->getGrantBasedSubmissionAuthorization()) {
                $this->authorizationService->registerSubmission($submission);
                $wasSubmissionAddedToAuthorization = true;
            }

            $this->entityManager->persist($submission);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            if ($wasSubmissionAddedToAuthorization) {
                $this->authorizationService->deregisterSubmission($submission);
            }
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR,
                'Submission could not be created!', self::ADDING_SUBMISSION_FAILED_ERROR_ID, ['message' => $e->getMessage()]);
        }
        $submission->setGrantedActions($this->authorizationService->getGrantedSubmissionItemActions($submission));

        $postEvent = new CreateSubmissionPostEvent($submission);
        $this->eventDispatcher->dispatch($postEvent);

        return $submission;
    }

    public function updateSubmission(Submission $submission): Submission
    {
        $this->assertSubmissionIsValid($submission);

        try {
            $this->entityManager->persist($submission);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            $apiError = ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Submission could not be updated!',
                self::UPDATING_SUBMISSION_FAILED_ERROR_ID, ['message' => $e->getMessage()]);
            throw $apiError;
        }

        return $submission;
    }

    public function removeSubmission(Submission $submission): void
    {
        try {
            $this->entityManager->remove($submission);
            $this->entityManager->flush();

            if ($submission->getForm()->getGrantBasedSubmissionAuthorization()) {
                try {
                    $this->authorizationService->deregisterSubmission($submission);
                } catch (\Exception $exception) {
                    $this->logger->warning(sprintf('Failed to remove submission resource \'%s\' from authorization: %s',
                        $submission->getIdentifier(), $exception->getMessage()));
                }
            }
        } catch (\Exception $exception) {
            $apiError = ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Submission could not be removed!',
                self::REMOVING_SUBMISSION_FAILED_ERROR_ID, ['message' => $exception->getMessage()]);
            throw $apiError;
        }
    }

    /**
     * @throws ApiError
     */
    public function addForm(Form $form): Form
    {
        $form->setIdentifier((string) Uuid::v4());
        $form->setDateCreated(new \DateTime('now'));
        $form->setCreatorId($this->authorizationService->getUserIdentifier());

        $this->assertFormIsValid($form);

        $wasFormAddedToAuthorization = false;
        try {
            $this->authorizationService->registerForm($form);
            $wasFormAddedToAuthorization = true;

            $this->entityManager->persist($form);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            if ($wasFormAddedToAuthorization) {
                $this->authorizationService->deregisterForm($form);
            }
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Form could not be created!',
                self::ADDING_FORM_FAILED_ERROR_ID, ['message' => $e->getMessage()]);
        }
        $form->setGrantedActions($this->authorizationService->getGrantedFormItemActions($form));

        return $form;
    }

    /**
     * @throws ApiError
     */
    public function removeForm(Form $form): void
    {
        try {
            if ($form->getGrantBasedSubmissionAuthorization()) {
                try {
                    $SUBMISSION_ENTITY_ALIAS = 's';
                    $queryBuilder = $this->entityManager->createQueryBuilder();
                    $formSubmissionIdentifiers = $queryBuilder
                        ->select($SUBMISSION_ENTITY_ALIAS.'.identifier')
                        ->from(Submission::class, $SUBMISSION_ENTITY_ALIAS)
                        ->where($queryBuilder->expr()->eq($SUBMISSION_ENTITY_ALIAS.'.form', ':formIdentifier'))
                        ->setParameter(':formIdentifier', $form->getIdentifier())
                        ->getQuery()
                        ->execute();
                    if (!empty($formSubmissionIdentifiers)) {
                        $this->authorizationService->deregisterSubmissionsByIdentifier($formSubmissionIdentifiers);
                    }
                } catch (\Exception $e) {
                    $this->logger->warning(sprintf('Failed to remove submission resources of form \'%s\' from authorization: %s',
                        $form->getIdentifier(), $e->getMessage()));
                }
            }

            $this->entityManager->remove($form);
            $this->entityManager->flush();

            // delete form from authorization
            try {
                $this->authorizationService->deregisterForm($form);
            } catch (\Exception $exception) {
                $this->logger->warning(sprintf('Failed to remove form resource \'%s\' from authorization: %s',
                    $form->getIdentifier(), $exception->getMessage()));
            }
        } catch (\Exception $exception) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR,
                'Form could not be removed!', self::REMOVING_FORM_FAILED_ERROR_ID,
                ['message' => $exception->getMessage()]);
        }
    }

    /**
     * @throws ApiError
     */
    public function updateForm(Form $form): Form
    {
        $this->assertFormIsValid($form);
        try {
            $this->entityManager->persist($form);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR,
                'Form could not be updated!', self::UPDATING_FORM_FAILED_ERROR_ID,
                ['message' => $e->getMessage()]);
        }

        return $form;
    }

    /**
     * Removes all submissions of the form which are in state 'submitted'.
     *
     * @throws ApiError
     */
    public function removeAllSubmittedFormSubmissions(string $formIdentifier): void
    {
        try {
            $SUBMISSION_ENTITY_ALIAS = 's';
            $queryBuilder = $this->entityManager->createQueryBuilder();
            $form = $this->entityManager->getRepository(Form::class)->findOneBy(['identifier' => $formIdentifier]);
            if ($form !== null && $form->getGrantBasedSubmissionAuthorization()) {
                $sql = 'DELETE FROM formalize_submissions
                    WHERE form_identifier = :formIdentifier AND submission_state = '.Submission::SUBMISSION_STATE_SUBMITTED.
                    ' RETURNING identifier';
                $query = $this->entityManager->createNativeQuery($sql, new ResultSetMapping());
                $query->setParameter(':formIdentifier', $formIdentifier);
                $formSubmissionIdentifiers = $query->getSingleColumnResult();
                if (!empty($formSubmissionIdentifiers)) {
                    $this->authorizationService->deregisterSubmissionsByIdentifier($formSubmissionIdentifiers);
                }
            } else {
                $queryBuilder
                    ->delete(Submission::class, $SUBMISSION_ENTITY_ALIAS)
                    ->andWhere($queryBuilder->expr()->eq($SUBMISSION_ENTITY_ALIAS.'.form', ':formIdentifier'))
                    ->andWhere($queryBuilder->expr()->eq(
                        $SUBMISSION_ENTITY_ALIAS.'.submissionState', Submission::SUBMISSION_STATE_SUBMITTED))
                    ->setParameter(':formIdentifier', $formIdentifier)
                    ->getQuery()
                    ->execute();
            }
        } catch (ApiError $e) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR,
                'Form submissions could not be removed!', self::REMOVING_FORM_SUBMISSIONS_FAILED,
                ['message' => $e->getMessage()]);
        }
    }

    /**
     * @throws ApiError
     */
    public function getForm(string $identifier): Form
    {
        $form = $this->getFormInternal($identifier);
        if ($form === null) {
            throw ApiError::withDetails(Response::HTTP_NOT_FOUND, 'Form could not be found',
                self::FORM_NOT_FOUND_ERROR_ID, [$identifier]);
        }

        return $form;
    }

    public function tryGetForm(string $identifier): ?Form
    {
        return $this->getFormInternal($identifier);
    }

    /**
     * @return Form[]
     */
    public function getFormsCurrentUserIsAuthorizedToRead(
        int $firstResultIndex, int $maxNumResults, array $filters = []): array
    {
        if ($filters[self::WHERE_CONTAINS_SUBMISSIONS_MAY_READ_FILTER] ?? false) {
            // TODO: allow to request all forms that comply or call serveral times here and merge result pages
            $grantedFormActionsPage = $this->authorizationService->getGrantedFormActionsPage(
                [ResourceActionGrantService::MANAGE_ACTION, AuthorizationService::READ_FORM_ACTION]);

            $submissionIdentifiersMayRead = array_keys(
                $this->authorizationService->getSubmissionItemActionsCurrentUserHasAReadGrantFor());

            $FORM_ENTITY_ALIAS = 'f';
            $SUBMISSION_ENTITY_ALIAS = 's';
            $queryBuilder = $this->entityManager->createQueryBuilder();
            $queryBuilder
                ->select($FORM_ENTITY_ALIAS)
                ->from(Form::class, $FORM_ENTITY_ALIAS)
                ->innerJoin(Submission::class, $SUBMISSION_ENTITY_ALIAS, Join::WITH,
                    "$SUBMISSION_ENTITY_ALIAS.form = $FORM_ENTITY_ALIAS.identifier");

            try {
                $filterTreeBuilder = FilterTreeBuilder::create()
                    ->or()
                        ->equals("$SUBMISSION_ENTITY_ALIAS.submissionState", Submission::SUBMISSION_STATE_DRAFT)
                        // CAUTION: the following works only while we don't have higher submission state than 'submitted'
                        ->equals("BIT_AND($FORM_ENTITY_ALIAS.allowedActionsWhenSubmitted, ".Form::READ_SUBMISSION_ACTION_FLAG.')',
                            Form::READ_SUBMISSION_ACTION_FLAG)
                    ->end()
                    ->or()
                        ->and()
                            ->equals("$FORM_ENTITY_ALIAS.grantBasedSubmissionAuthorization", '0')
                            ->equals("$SUBMISSION_ENTITY_ALIAS.creatorId", $this->authorizationService->getUserIdentifier())
                        ->end();
                if (!empty($submissionIdentifiersMayRead)) {
                    $filterTreeBuilder
                        ->and()
                        ->equals("$FORM_ENTITY_ALIAS.grantBasedSubmissionAuthorization", '1')
                        ->inArray("$SUBMISSION_ENTITY_ALIAS.identifier", $submissionIdentifiersMayRead)
                        ->end();
                }
                $filter = $filterTreeBuilder
                    ->end()
                    ->createFilter();

                QueryHelper::addFilter($queryBuilder, $filter, null);
                $queryBuilder->groupBy("$FORM_ENTITY_ALIAS.identifier");
            } catch (\Exception $exception) {
                throw new \RuntimeException('adding filter failed: '.$exception->getMessage());
            }

            /** @var Form[] $formsWithSubmissionsMayRead */
            $formsWithSubmissionsMayRead = $queryBuilder
                ->getQuery()
                ->getResult();

            $resultFormPage = [];
            $currentFormIndex = 0;
            foreach ($formsWithSubmissionsMayRead as $formWithSubmissionsMayRead) {
                if (count($resultFormPage) >= $maxNumResults) {
                    break;
                }
                if ($grantedFormActions = $grantedFormActionsPage[$formWithSubmissionsMayRead->getIdentifier()] ?? null) {
                    if ($currentFormIndex >= $firstResultIndex) {
                        $formWithSubmissionsMayRead->setGrantedActions($grantedFormActions);
                        $resultFormPage[] = $formWithSubmissionsMayRead;
                    }
                    ++$currentFormIndex;
                }
            }

            return $resultFormPage;
        }

        if ($filters[self::WHERE_READ_FORM_SUBMISSIONS_GRANTED_FILTER] ?? false) {
            $grantedFormActionsPage = Pagination::getPage($firstResultIndex, $maxNumResults,
                function (int $currentStartIndex, int $maxNumPageItems) {
                    return $this->authorizationService->getGrantedFormActionsPage(
                        [ResourceActionGrantService::MANAGE_ACTION, AuthorizationService::READ_SUBMISSIONS_FORM_ACTION],
                        $currentStartIndex, $maxNumPageItems);
                },
                function (array $grantedFormActions) {
                    return in_array(AuthorizationService::READ_FORM_ACTION, $grantedFormActions, true)
                        || in_array(ResourceActionGrantService::MANAGE_ACTION, $grantedFormActions, true);
                }, min(AuthorizationService::MAX_NUM_RESULTS_MAX, (int) ($maxNumResults * 1.5)));
        } else {
            $grantedFormActionsPage = $this->authorizationService->getGrantedFormActionsPage(
                [ResourceActionGrantService::MANAGE_ACTION, AuthorizationService::READ_FORM_ACTION],
                $firstResultIndex, $maxNumResults);
        }

        $resultFormPage = $this->getFormsInternal(0 /* sic! */, $maxNumResults, array_keys($grantedFormActionsPage));
        $currentFormIndex = 0;
        foreach ($grantedFormActionsPage as $formActions) {
            $resultFormPage[$currentFormIndex++]->setGrantedActions($formActions);
        }

        return $resultFormPage;
    }

    /**
     * @return Submission[]
     *
     * @throws ApiError
     */
    public function getFormSubmissionsCurrentUserIsAuthorizedToRead(string $formIdentifier,
        int $firstResultIndex, int $maxNumResults): array
    {
        $form = $this->getForm($formIdentifier);
        if (in_array(AuthorizationService::READ_SUBMISSIONS_FORM_ACTION, $form->getGrantedActions(), true)) {
            $submissionsMayRead = $this->getSubmittedFormSubmissions($formIdentifier,
                firstResultIndex: $firstResultIndex,
                maxNumResults: $maxNumResults);
            $grantedSubmissionItemActions =
                $this->authorizationService->getGrantedSubmissionItemActionsFormLevel($form);
            foreach ($submissionsMayRead as $submission) {
                $submission->setGrantedActions($grantedSubmissionItemActions);
            }
        } else {
            $submissionItemActionsCurrentUserHasAReadGrantFor = [];
            try {
                $filterTreeBuilder = FilterTreeBuilder::create()
                    ->equals('form', $formIdentifier);

                if ($form->getGrantBasedSubmissionAuthorization()) {
                    $submissionItemActionsCurrentUserHasAReadGrantFor =
                        $this->authorizationService->getSubmissionItemActionsCurrentUserHasAReadGrantFor();

                    if (empty($submissionItemActionsCurrentUserHasAReadGrantFor)) {
                        return [];
                    } else {
                        $filterTreeBuilder
                            ->inArray('identifier', array_keys($submissionItemActionsCurrentUserHasAReadGrantFor));
                    }
                } else { // creator-based submission authorization
                    $filterTreeBuilder
                        ->equals('creatorId', $this->authorizationService->getUserIdentifier());
                }

                // if submissions in submitted state mustn't be read -> require them to be drafts
                if (false === $form->isAllowedSubmissionActionWhenSubmitted(AuthorizationService::READ_SUBMISSION_ACTION)) {
                    $filterTreeBuilder
                        ->equals('submissionState', Submission::SUBMISSION_STATE_DRAFT);
                }

                $filter = $filterTreeBuilder->createFilter();
            } catch (\Exception $exception) {
                throw new \RuntimeException('adding filter failed: '.$exception->getMessage());
            }

            try {
                /** @var Submission[] $submissionsMayRead */
                $submissionsMayRead = QueryHelper::getEntities(Submission::class,
                    $this->entityManager, $firstResultIndex, $maxNumResults, $filter);
            } catch (\Exception $exception) {
                throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getMessage(),
                    self::GETTING_SUBMISSION_COLLECTION_FAILED_ERROR_ID, [$formIdentifier]);
            }

            foreach ($submissionsMayRead as $submission) {
                $submission->setGrantedActions(
                    $this->authorizationService->getGrantedSubmissionItemActionsSubmissionLevel($submission,
                        $form->getGrantBasedSubmissionAuthorization() ?
                            $submissionItemActionsCurrentUserHasAReadGrantFor[$submission->getIdentifier()] : []));
            }
        }

        return $submissionsMayRead;
    }

    /**
     * @throws ApiError
     */
    private function getFormInternal(string $identifier): ?Form
    {
        $form = $this->entityManager->getRepository(Form::class)->findOneBy(['identifier' => $identifier]);
        if ($form !== null) {
            $form->setGrantedActions($this->authorizationService->getGrantedFormItemActions($form));
        }

        return $form;
    }

    /**
     * @return Form[]
     *
     * @throws ApiError
     */
    private function getFormsInternal(int $firstResultIndex, int $maxNumResults, ?array $whereIdentifierInArray = null): array
    {
        $FORM_ENTITY_ALIAS = 'f';
        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder
            ->select($FORM_ENTITY_ALIAS)
            ->from(Form::class, $FORM_ENTITY_ALIAS);

        if ($whereIdentifierInArray !== null) {
            $queryBuilder
                ->where($queryBuilder->expr()->in($FORM_ENTITY_ALIAS.'.identifier', ':identifierInArray'))
                ->setParameter(':identifierInArray', $whereIdentifierInArray);
        }

        $forms = $queryBuilder->getQuery()
            ->setFirstResult($firstResultIndex)
            ->setMaxResults($maxNumResults)
            ->getResult();

        // select with the 'in' operator doesn't keep the original order of identifiers in the array,
        // so we restore the original order of identifiers:
        if ($whereIdentifierInArray !== null) {
            $formsMap = [];
            foreach ($forms as $form) {
                $formsMap[$form->getIdentifier()] = $form;
            }
            $formsInOriginalOrder = [];
            foreach ($whereIdentifierInArray as $identifier) {
                // form could have been deleted from the database manually
                if ($form = $formsMap[$identifier] ?? null) {
                    $formsInOriginalOrder[] = $form;
                } else {
                    $this->logger->warning(sprintf('form "%s" seems to exist in authz tables but not in the form table. maybe was deleted manually', $identifier));
                }
            }
            $forms = $formsInOriginalOrder;
        }

        return $forms;
    }

    /**
     * @param string[]|null $whereSubmissionIdentifiersIn
     * @param string[]|null $whereFormIdentifiersNotIn
     */
    private function createGetSubmissionsQueryBuilder(string $select,
        ?string $whereFormIdentifierEquals = null,
        ?int $whereSubmissionStateEquals = null,
        ?array $whereFormIdentifiersNotIn = null,
        ?array $whereSubmissionIdentifiersIn = null,

        array $filters = [],
        int $firstResultIndex = 0, ?int $maxNumResults = null): QueryBuilder
    {
        $SUBMISSION_ENTITY_ALIAS = self::SUBMISSION_ENTITY_ALIAS;
        $FORM_ENTITY_ALIAS = self::FORM_ENTITY_ALIAS;

        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder
            ->select($select)
            ->from(Submission::class, $SUBMISSION_ENTITY_ALIAS);

        if (($filters[self::OUTPUT_VALIDATION_FILTER] ?? null) === self::OUTPUT_VALIDATION_KEYS) {
            $this->entityManager->getConfiguration()->addCustomStringFunction('JSON_KEYS', JsonKeys::class);
            $this->entityManager->getConfiguration()->addCustomStringFunction('JSON_EXTRACT', JsonExtract::class);

            $queryBuilder
                ->innerJoin(Form::class, $FORM_ENTITY_ALIAS, Join::WITH,
                    "$SUBMISSION_ENTITY_ALIAS.form = $FORM_ENTITY_ALIAS.identifier")
                ->andWhere("JSON_KEYS(JSON_EXTRACT($FORM_ENTITY_ALIAS.dataFeedSchema, '$.properties')) = JSON_KEYS($SUBMISSION_ENTITY_ALIAS.dataFeedElement)");
        }

        // TODO: replace filters by one Filter parameter
        if ($whereFormIdentifierEquals !== null) {
            $queryBuilder
                ->andWhere($queryBuilder->expr()->eq("$SUBMISSION_ENTITY_ALIAS.form", ':formIdentifier'))
                ->setParameter(':formIdentifier', $whereFormIdentifierEquals);
        }
        if ($whereSubmissionStateEquals !== null) {
            $queryBuilder
                ->andWhere($queryBuilder->expr()->eq("$SUBMISSION_ENTITY_ALIAS.submissionState", ':submissionState'))
                ->setParameter(':submissionState', $whereSubmissionStateEquals);
        }
        if (!empty($whereSubmissionIdentifiersIn)) {
            $queryBuilder
                ->andWhere($queryBuilder->expr()->in("$SUBMISSION_ENTITY_ALIAS.identifier", ':submissionIdentifiers'))
                ->setParameter(':submissionIdentifiers', $whereSubmissionIdentifiersIn);
        }
        if (!empty($whereFormIdentifiersNotIn)) {
            $queryBuilder
                ->andWhere($queryBuilder->expr()->notIn("$SUBMISSION_ENTITY_ALIAS.form", ':formIdentifiers'))
                ->setParameter(':formIdentifiers', $whereFormIdentifiersNotIn);
        }

        $queryBuilder->setFirstResult($firstResultIndex);
        if ($maxNumResults !== null) {
            $queryBuilder->setMaxResults($maxNumResults);
        }

        return $queryBuilder;
    }

    /**
     * @throws ApiError if the form is invalid
     */
    private function assertFormIsValid(Form $form): void
    {
        if ($form->getName() === null) {
            throw ApiError::withDetails(Response::HTTP_UNPROCESSABLE_ENTITY,
                'field \'name\' is required', self::REQUIRED_FIELD_MISSION_ID, ['name']);
        }

        $dataFeedSchemaObject = null;
        if (($dataFeedSchema = $form->getDataFeedSchema()) !== null) {
            try {
                $dataFeedSchemaObject = json_decode($dataFeedSchema, false, flags: JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                throw ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                    '\'dataFeedSchema\' is not valid JSON',
                    self::FORM_INVALID_DATA_FEED_SCHEMA_ERROR_ID, [$exception->getMessage()]);
            }
        }

        if ($dataFeedSchemaObject !== null) {
            try {
                // create a dummy object to validate the JSON schema against
                $dummyDataObject = (object) [];
                $jsonSchemaValidator = new Validator();
                $jsonSchemaValidator->validate(
                    $dummyDataObject, $dataFeedSchemaObject,
                    Constraint::CHECK_MODE_VALIDATE_SCHEMA | Constraint::CHECK_MODE_EXCEPTIONS);
            } catch (InvalidSchemaException $exception) {
                throw ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                    '\'dataFeedSchema\' is not a valid JSON schema',
                    self::FORM_INVALID_DATA_FEED_SCHEMA_ERROR_ID,
                    $exception->getPrevious() !== null ? [$exception->getPrevious()->getMessage()] : []);
            } catch (ValidationException) {
                // only validate the schema, ignoring validation errors
                // caused by the dummy JSON value object not complying with the schema
            }
        }
    }

    private function assertSubmissionIsValid(Submission $submission): void
    {
        if ($submission->getForm() === null) {
            throw ApiError::withDetails(Response::HTTP_UNPROCESSABLE_ENTITY,
                'field \'form\' is required', self::REQUIRED_FIELD_MISSION_ID, ['form']);
        }
        if (false === $submission->getForm()->isAllowedSubmissionState($submission->getSubmissionState())) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                'The submission state \''.$submission->getSubmissionState().'\' is not allowed for the form',
                self::SUBMISSION_STATE_NOT_ALLOWED_ERROR_ID,
                [$submission->getSubmissionState()]);
        }

        if ($submission->isSubmitted()) {
            $this->assertFormIsAvailable($submission->getForm());
            $this->assertDataIsValid($submission);
        }
    }

    /**
     * @throws ApiError if the data of the submission is invalid
     */
    private function assertDataIsValid(Submission $submission): void
    {
        if ($submission->getDataFeedElement() === null) {
            throw ApiError::withDetails(Response::HTTP_UNPROCESSABLE_ENTITY,
                'field \'dataFeedElement\' is required', self::REQUIRED_FIELD_MISSION_ID, ['dataFeedElement']);
        }

        try {
            $dataObject = json_decode($submission->getDataFeedElement(), false, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                'The dataFeedElement doesn\'t contain valid json!',
                self::SUBMISSION_DATA_FEED_ELEMENT_INVALID_JSON_ERROR_ID);
        }

        $form = $submission->getForm();
        if ($form->getDataFeedSchema() === null) {
            $form->setDataFeedSchema($this->generateDataFeedSchemaFromDataFeedElement($submission->getDataFeedElement()));
            $this->updateForm($form);
        }

        try {
            $dataSchemaObject = json_decode($form->getDataFeedSchema(), false, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $jsonException) {
            throw new \RuntimeException('Unexpected: dataFeedSchema is not valid JSON: '.$jsonException->getMessage());
        }

        $jsonSchemaValidator = new Validator();
        if ($jsonSchemaValidator->validate($dataObject, $dataSchemaObject) !== Validator::ERROR_NONE) {
            $errorDetails = array_map(function ($error) {
                return (!Tools::isNullOrEmpty($error['property'] ?? null) ? $error['property'].': ' : '').($error['message'] ?? '');
            }, $jsonSchemaValidator->getErrors());
            if ($this->debug) {
                $this->logger->warning('The dataFeedElement doesn\'t comply with the form\'s data schema: '.implode('; ', $errorDetails));
                $this->logger->warning('dataFeedElement: '.$submission->getDataFeedElement());
            }
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                'The dataFeedElement doesn\'t comply with the form\'s data schema',
                self::SUBMISSION_DATA_FEED_ELEMENT_INVALID_SCHEMA_ERROR_ID,
                $errorDetails);
        }
    }

    private function generateDataFeedSchemaFromDataFeedElement(string $dataFeedElement): string
    {
        // IDEA: Add support for non-required properties (values that are null or missing)
        // by progressively updating @autogenerated schemas as soon as we get a non-null value
        // IDEA: Improve support for arrays: Update the (initially guessed) array item type
        // as soon as we get a non-empty array.
        try {
            $schema = [];
            $schema['$comment'] = '@autogenerated_from_json_object';
            $schema['type'] = 'object';
            $schema['properties'] = [];

            $data = json_decode($dataFeedElement, true, flags: JSON_THROW_ON_ERROR);
            foreach ($data as $key => $value) {
                $property = [
                    'type' => self::getJSONTypeFor($value),
                ];
                if (is_array($value)) {
                    $property['items'] = [
                        // for empty arrays guess item type to be string
                        'type' => self::getJSONTypeFor($value[0] ?? ''),
                        '$comment' => '@guessed_items_type',
                    ];
                }

                $schema['properties'][$key] = $property;
            }
            if ($data !== []) {
                $schema['required'] = array_keys($data);
            }
            $schema['additionalProperties'] = false; // maybe allow additional properties to come?

            return json_encode($schema, flags: JSON_THROW_ON_ERROR);
        } catch (\Exception $exception) {
            throw new \RuntimeException(
                'unexpected: auto-generating JSON schema from submission data failed:'.$exception->getMessage());
        }
    }

    /**
     * @throws \Exception
     */
    private static function getJSONTypeFor(mixed $value): string
    {
        return match (gettype($value)) {
            'string' => 'string',
            'integer' => 'integer',
            'double' => 'number',
            'boolean' => 'boolean',
            'array' => 'array',
            'NULL' => 'null',
            default => throw new \Exception('unsupported value type'),
        };
    }

    private function assertFormIsAvailable(Form $form): void
    {
        try {
            $dateTimeNowUtc = new \DateTime('now', new \DateTimeZone('UTC'));
        } catch (\Exception $exception) {
            throw new \RuntimeException($exception->getMessage());
        }
        if (($form->getAvailabilityStarts() !== null && $dateTimeNowUtc < $form->getAvailabilityStarts())
            || ($form->getAvailabilityEnds() !== null && $dateTimeNowUtc > $form->getAvailabilityEnds())) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                'The specified form is currently not available', self::SUBMISSION_FORM_CURRENTLY_NOT_AVAILABLE_ERROR_ID);
        }
    }

    private static function nullIfEmpty(?array $whereFormIdentifierNotIn): ?array
    {
        return empty($whereFormIdentifierNotIn) ? null : $whereFormIdentifierNotIn;
    }
}
