<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Service;

use Dbp\Relay\AuthorizationBundle\API\ResourceActionGrantService;
use Dbp\Relay\CoreBundle\Doctrine\QueryHelper;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\Helpers\Tools;
use Dbp\Relay\CoreBundle\Rest\Query\Filter\Filter;
use Dbp\Relay\CoreBundle\Rest\Query\Filter\FilterException;
use Dbp\Relay\CoreBundle\Rest\Query\Filter\FilterTreeBuilder;
use Dbp\Relay\CoreBundle\Rest\Query\Pagination\Pagination;
use Dbp\Relay\FormalizeBundle\Authorization\AuthorizationService;
use Dbp\Relay\FormalizeBundle\Entity\Form;
use Dbp\Relay\FormalizeBundle\Entity\Submission;
use Dbp\Relay\FormalizeBundle\Entity\SubmittedFile;
use Dbp\Relay\FormalizeBundle\Event\CreateSubmissionPostEvent;
use Dbp\Relay\FormalizeBundle\Event\SubmissionSubmittedPostEvent;
use Dbp\Relay\FormalizeBundle\Event\SubmittedSubmissionUpdatedPostEvent;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\Query\ResultSetMapping;
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
    public const WHERE_MAY_READ_SUBMISSIONS_FILTER = 'whereMayReadSubmissions';
    public const OUTPUT_VALIDATION_FILTER = 'outputValidation';
    public const OUTPUT_VALIDATION_KEYS = 'KEYS';

    public const FORM_NOT_FOUND_ERROR_ID = 'formalize:form-with-id-not-found';
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
    public const MAX_NUM_FORM_SUBMISSIONS_PER_CREATOR_REACHED_ERROR_ID = 'formalize:max-num-form-submissions-per-creator-reached';
    public const SUBMISSION_SUBMITTED_FILES_INVALID_SCHEMA_ERROR_ID = 'formalize:submission-submitted-files-invalid-schema';

    private const SUBMISSION_ENTITY_ALIAS = 's';
    private const FORM_ENTITY_ALIAS = 'f';

    /** @var int */
    private const FILE_SCHEMA_MIN_NUMBER_DEFAULT = 1;
    /** @var int */
    private const FILE_SCHEMA_MAX_NUMBER_DEFAULT = 1;
    /** @var int */
    private const FILE_SCHEMA_MAX_SIZE_MB_DEFAULT = 10;
    /** @var string[] */
    private const FILE_SCHEMA_ALLOWED_MIME_TYPES_DEFAULT = [];

    private const FORM_SCHEMA_FILES_ATTRIBUTE = 'files';
    private const FILE_SCHEMA_MIN_NUMBER_ATTRIBUTE = 'minNumber';
    private const FILE_SCHEMA_MAX_NUMBER_ATTRIBUTE = 'maxNumber';
    private const FILE_SCHEMA_MAX_SIZE_MB_ATTRIBUTE = 'maxSizeMb';
    private const FILE_SCHEMA_ALLOWED_MIME_TYPES_ATTRIBUTE = 'allowedMimeTypes';
    public const FILE_SCHEMA_ADDITIONAL_FILES_ATTRIBUTE = 'additionalFiles';

    /** @var int */
    private const BYTES_PER_MB = 1048576;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AuthorizationService $authorizationService,
        private readonly SubmittedFileService $submittedFileService,
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
        $SUBMISSION_ENTITY_ALIAS = self::SUBMISSION_ENTITY_ALIAS;

        try {
            $filter = FilterTreeBuilder::create()
                ->equals("$SUBMISSION_ENTITY_ALIAS.form", $formIdentifier)
                ->equals("$SUBMISSION_ENTITY_ALIAS.submissionState", Submission::SUBMISSION_STATE_SUBMITTED)
                ->createFilter();
        } catch (FilterException $filterException) {
            throw new \RuntimeException('invalid get submissions filter: '.$filterException->getMessage());
        }

        return $this->getSubmissions($filter, $filters, $firstResultIndex, $maxNumResults);
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
            throw ApiError::withDetails(Response::HTTP_NOT_FOUND, 'Submission was not found!',
                self::SUBMISSION_NOT_FOUND_ERROR_ID, [$identifier]);
        }

        $this->submittedFileService->setSubmittedFilesDetails($submission);

        $submission->setGrantedActions(
            $this->authorizationService->getGrantedSubmissionItemActions($submission));

        return $submission;
    }

    /**
     * @throws ApiError
     */
    public function addSubmission(Submission $submission): Submission
    {
        $submission->setIdentifier((string) Uuid::v7());

        if (($formIdentifier = $submission->getForm()?->getIdentifier()) !== null
            && ($currentUserIdentifier = $this->authorizationService->getUserIdentifier()) !== null) {
            try {
                $filter = FilterTreeBuilder::create()
                    ->equals(self::SUBMISSION_ENTITY_ALIAS.'.form', $formIdentifier)
                    ->equals(self::SUBMISSION_ENTITY_ALIAS.'.creatorId', $currentUserIdentifier)
                    ->createFilter();
            } catch (FilterException $filterException) {
                throw new \RuntimeException('creating get creator submissions filter failed: '.$filterException->getMessage());
            }

            if (count($this->getSubmissions($filter)) >= $submission->getForm()->getMaxNumSubmissionsPerCreator()) {
                throw ApiError::withDetails(Response::HTTP_FORBIDDEN,
                    'You have reached the maximum number of submissions allowed for this form!',
                    self::MAX_NUM_FORM_SUBMISSIONS_PER_CREATOR_REACHED_ERROR_ID);
            }
        }

        $this->validateSubmission($submission, null);

        $now = new \DateTime('now');
        $submission->setDateCreated($now);
        $submission->setDateLastModified($now);
        $submission->setCreatorId($this->authorizationService->getUserIdentifier());

        $wasSubmissionAddedToAuthorization = false;
        $wasSubmittedFileChangesCommited = false;
        try {
            if ($submission->getForm()->getGrantBasedSubmissionAuthorization()) {
                $this->authorizationService->registerSubmission($submission);
                $wasSubmissionAddedToAuthorization = true;
            }

            $this->submittedFileService->applySubmittedFileChanges($submission);
            $wasSubmittedFileChangesCommited = true;

            $this->entityManager->persist($submission);
            $this->entityManager->flush();
        } catch (\Exception $exception) {
            if ($wasSubmissionAddedToAuthorization) {
                try {
                    $this->authorizationService->deregisterSubmission($submission);
                } catch (\Exception $exception) { // ignore rollback errors
                    $this->logger->warning(sprintf('failed to de-register submission "%s" from authorization: %s',
                        $submission->getIdentifier(), $exception->getMessage()));
                }
            }
            if ($wasSubmittedFileChangesCommited) {
                // TODO: rollback strategy
            }
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR,
                'Submission could not be created', self::ADDING_SUBMISSION_FAILED_ERROR_ID);
        }
        $submission->setGrantedActions($this->authorizationService->getGrantedSubmissionItemActions($submission));

        if ($submission->isSubmitted()) {
            $postEvent = new SubmissionSubmittedPostEvent($submission);
            $this->eventDispatcher->dispatch($postEvent);

            // remove in one of next versions
            $postEvent = new CreateSubmissionPostEvent($submission);
            $this->eventDispatcher->dispatch($postEvent);
        }

        return $submission;
    }

    /**
     * @throws ApiError
     */
    public function updateSubmission(Submission $submission, Submission $previousSubmission): Submission
    {
        $this->validateSubmission($submission, $previousSubmission);

        $submission->setDateLastModified(new \DateTime('now'));

        $wereSubmittedFileChangesCommited = false;
        try {
            $this->submittedFileService->applySubmittedFileChanges($submission);
            $wereSubmittedFileChangesCommited = true;

            $this->entityManager->persist($submission);
            $this->entityManager->flush();
        } catch (\Exception) {
            if ($wereSubmittedFileChangesCommited) {
                // TODO: file rollback strategy
            }
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR,
                'Submission could not be updated',
                self::UPDATING_SUBMISSION_FAILED_ERROR_ID);
        }
        $this->submittedFileService->setSubmittedFilesDetails($submission);

        $submission->setGrantedActions(
            $this->authorizationService->getGrantedSubmissionItemActions($submission));

        if ($submission->isSubmitted()) {
            if (false === $previousSubmission->isSubmitted()) {
                $postEvent = new SubmissionSubmittedPostEvent($submission);
            } else {
                $postEvent = new SubmittedSubmissionUpdatedPostEvent($submission);
            }
            $this->eventDispatcher->dispatch($postEvent);
        }

        return $submission;
    }

    public function removeSubmission(Submission $submission): void
    {
        try {
            $this->entityManager->remove($submission);
            $this->entityManager->flush();

            try {
                $this->submittedFileService->removeFilesBySubmissionIdentifier($submission->getIdentifier());
            } catch (\Exception $exception) {
                $this->logger->warning(sprintf('Failed to remove submitted files for submission \'%s\': %s',
                    $submission->getIdentifier(), $exception->getMessage()));
            }

            if ($submission->getForm()->getGrantBasedSubmissionAuthorization()) {
                try {
                    $this->authorizationService->deregisterSubmission($submission);
                } catch (\Exception $exception) {
                    $this->logger->warning(sprintf('Failed to remove submission resource \'%s\' from authorization: %s',
                        $submission->getIdentifier(), $exception->getMessage()));
                }
            }
        } catch (\Exception $exception) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR,
                'Submission could not be removed',
                self::REMOVING_SUBMISSION_FAILED_ERROR_ID);
        }
    }

    /**
     * @throws ApiError
     */
    public function addForm(Form $form, ?string $formManagerUserIdentifier = null, bool $setIdentifier = true): Form
    {
        $formManagerUserIdentifier ??= $this->authorizationService->getUserIdentifier();

        $this->validateForm($form);

        if ($setIdentifier) {
            $form->setIdentifier((string) Uuid::v7());
        } elseif (false === Uuid::isValid($form->getIdentifier() ?? '')) {
            throw ApiError::withDetails(Response::HTTP_UNPROCESSABLE_ENTITY,
                'field \'identifier\' is required and must be a valid UUID',
                self::REQUIRED_FIELD_MISSION_ID, ['identifier']);
        }
        $form->setDateCreated(new \DateTime('now'));
        $form->setCreatorId($formManagerUserIdentifier);

        $wasFormAddedToAuthorization = false;
        try {
            $this->authorizationService->registerForm($form, $formManagerUserIdentifier);
            $wasFormAddedToAuthorization = true;

            $this->entityManager->persist($form);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            if ($wasFormAddedToAuthorization) {
                $this->authorizationService->deregisterForm($form);
            }
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Form could not be created!',
                self::ADDING_FORM_FAILED_ERROR_ID);
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
            try {
                $SUBMISSION_ENTITY_ALIAS = 's';
                $queryBuilder = $this->entityManager->createQueryBuilder();
                $formSubmissionIdentifiers = $queryBuilder
                    ->select($SUBMISSION_ENTITY_ALIAS.'.identifier')
                    ->from(Submission::class, $SUBMISSION_ENTITY_ALIAS)
                    ->where($queryBuilder->expr()->eq($SUBMISSION_ENTITY_ALIAS.'.form', ':formIdentifier'))
                    ->setParameter(':formIdentifier', $form->getIdentifier())
                    ->getQuery()
                    ->getSingleColumnResult();
                $this->doFormSubmissionCleanup($form, $formSubmissionIdentifiers);
            } catch (\Exception $exception) {
                $this->logger->error(sprintf('Failed to get submission identifiers for form \'%s\': %s',
                    $form->getIdentifier(), $exception->getMessage()));
                throw $exception;
            }

            try {
                $this->authorizationService->deregisterForm($form);
            } catch (\Exception $exception) {
                $this->logger->error(sprintf('Failed to remove form resource \'%s\' from authorization: %s',
                    $form->getIdentifier(), $exception->getMessage()));
                throw $exception;
            }

            $this->entityManager->remove($form);
            $this->entityManager->flush();
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
        $this->validateForm($form);
        try {
            $this->entityManager->persist($form);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR,
                'Form could not be updated!', self::UPDATING_FORM_FAILED_ERROR_ID);
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
            $form = $this->entityManager->getRepository(Form::class)->findOneBy(['identifier' => $formIdentifier]);
            if ($form !== null) {
                $sql = 'DELETE FROM formalize_submissions
                    WHERE form_identifier = :formIdentifier AND submission_state IN ('.
                    Submission::SUBMISSION_STATE_SUBMITTED.', '.Submission::SUBMISSION_STATE_ACCEPTED.
                    ') RETURNING identifier';
                $query = $this->entityManager->createNativeQuery($sql, new ResultSetMapping());
                $query->setParameter(':formIdentifier', $formIdentifier);
                $formSubmissionIdentifiers = $query->getSingleColumnResult();
                $this->doFormSubmissionCleanup($form, $formSubmissionIdentifiers);
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
        if ($filters[self::WHERE_MAY_READ_SUBMISSIONS_FILTER] ?? false) {
            $grantedFormActions = $this->authorizationService->getGrantedFormActions(
                [ResourceActionGrantService::MANAGE_ACTION, AuthorizationService::READ_FORM_ACTION]);
            if ([] === $grantedFormActions) {
                return [];
            }

            $formIdentifiersWhereReadFormSubmissionsGranted = [];
            foreach ($grantedFormActions as $formIdentifier => $grantedActions) {
                if (in_array(AuthorizationService::READ_SUBMISSIONS_FORM_ACTION, $grantedActions, true)
                    || in_array(ResourceActionGrantService::MANAGE_ACTION, $grantedActions, true)) {
                    $formIdentifiersWhereReadFormSubmissionsGranted[] = $formIdentifier;
                }
            }

            $submissionIdentifiersMayRead = array_keys(
                $this->authorizationService->getSubmissionItemActionsCurrentUserHasAReadGrantFor());

            $FORM_ENTITY_ALIAS = self::FORM_ENTITY_ALIAS;
            $SUBMISSION_ENTITY_ALIAS = self::SUBMISSION_ENTITY_ALIAS;
            $queryBuilder = $this->entityManager->createQueryBuilder()
                ->select($FORM_ENTITY_ALIAS)
                ->from(Form::class, $FORM_ENTITY_ALIAS)
                ->leftJoin(Submission::class, $SUBMISSION_ENTITY_ALIAS, Join::WITH,
                    "$SUBMISSION_ENTITY_ALIAS.form = $FORM_ENTITY_ALIAS.identifier");

            try {
                $filterTreeBuilder = FilterTreeBuilder::create()
                    // forms the user may read and
                    ->inArray("$FORM_ENTITY_ALIAS.identifier", array_keys($grantedFormActions))
                    ->or()
                        // forms where the user has read (all) submissions permission ...
                        ->inArray("$FORM_ENTITY_ALIAS.identifier", $formIdentifiersWhereReadFormSubmissionsGranted)
                        // ... or forms where the user has read permissions for single submissions
                        ->and()
                            ->or()
                                // drafts ...
                                ->equals("$SUBMISSION_ENTITY_ALIAS.submissionState", Submission::SUBMISSION_STATE_DRAFT)
                                // ... or submissions from forms that allow submitted submissions to be read:
                                ->equals("BIT_AND($FORM_ENTITY_ALIAS.allowedActionsWhenSubmitted, ".Form::READ_SUBMISSION_ACTION_FLAG.')',
                                    Form::READ_SUBMISSION_ACTION_FLAG)
                            ->end()
                            ->or();
                if (($currentUserIdentifier = $this->authorizationService->getUserIdentifier()) !== null) {
                    $filterTreeBuilder
                                // submissions that the current user created (for creator-based submission authorization) or
                                ->and()
                                    ->equals("$FORM_ENTITY_ALIAS.grantBasedSubmissionAuthorization", '0')
                                    ->equals("$SUBMISSION_ENTITY_ALIAS.creatorId", $currentUserIdentifier)
                                ->end();
                }
                if ([] !== $submissionIdentifiersMayRead) {
                    $filterTreeBuilder
                                // submissions that the current user has read grants for (for grant-based submission authorization)
                                ->and()
                                    ->equals("$FORM_ENTITY_ALIAS.grantBasedSubmissionAuthorization", '1')
                                    ->inArray("$SUBMISSION_ENTITY_ALIAS.identifier", $submissionIdentifiersMayRead)
                                ->end();
                }
                $filter = $filterTreeBuilder
                            ->end() // or()
                        ->end() // and()
                    ->end() // or()
                    ->createFilter();

                QueryHelper::addFilter($queryBuilder, $filter);
                $queryBuilder->groupBy("$FORM_ENTITY_ALIAS.identifier");
            } catch (\Exception $exception) {
                throw new \RuntimeException('adding filter failed: '.$exception->getMessage());
            }

            /** @var Form[] $formsWithSubmissionsMayRead */
            $formsWithSubmissionsMayRead = $queryBuilder
                ->getQuery()
                ->setFirstResult($firstResultIndex)
                ->setMaxResults($maxNumResults)
                ->getResult();

            foreach ($formsWithSubmissionsMayRead as $formWithSubmissionsMayRead) {
                $formWithSubmissionsMayRead->setGrantedActions(
                    $grantedFormActions[$formWithSubmissionsMayRead->getIdentifier()]);
            }

            return $formsWithSubmissionsMayRead; // $resultFormPage;
        }

        if ($filters[self::WHERE_READ_FORM_SUBMISSIONS_GRANTED_FILTER] ?? false) {
            $grantedFormActions = Pagination::getPage($firstResultIndex, $maxNumResults,
                function (int $currentPageStartIndex, int $maxNumPageItems) {
                    return $this->authorizationService->getGrantedFormActions(
                        [ResourceActionGrantService::MANAGE_ACTION, AuthorizationService::READ_SUBMISSIONS_FORM_ACTION],
                        $currentPageStartIndex, $maxNumPageItems);
                },
                function (array $grantedFormActions) {
                    return in_array(AuthorizationService::READ_FORM_ACTION, $grantedFormActions, true)
                        || in_array(ResourceActionGrantService::MANAGE_ACTION, $grantedFormActions, true);
                }, min(AuthorizationService::MAX_NUM_RESULTS_MAX, (int) ($maxNumResults * 1.5)), true);
        } else {
            $grantedFormActions = $this->authorizationService->getGrantedFormActions(
                [ResourceActionGrantService::MANAGE_ACTION, AuthorizationService::READ_FORM_ACTION],
                $firstResultIndex, $maxNumResults);
        }

        $resultFormPage = $this->getFormsInternal(0 /* sic! */, $maxNumResults, array_keys($grantedFormActions));
        $currentFormIndex = 0;
        foreach ($grantedFormActions as $formActions) {
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
        int $firstResultIndex, int $maxNumResults, array $filters = []): array
    {
        try {
            $SUBMISSION_ENTITY_ALIAS = self::SUBMISSION_ENTITY_ALIAS;
            $form = $this->getForm($formIdentifier);
            $filterTreeBuilder = FilterTreeBuilder::create()
                ->equals("$SUBMISSION_ENTITY_ALIAS.form", $formIdentifier);

            if (in_array(AuthorizationService::READ_SUBMISSIONS_FORM_ACTION, $form->getGrantedActions(), true)
                || in_array(AuthorizationService::MANAGE_ACTION, $form->getGrantedActions(), true)) {
                // user has form level read submissions permission
                $submissionItemActionsCurrentUserHasAReadGrantFor = [];
                $filterTreeBuilder
                    ->or()
                       ->not()
                          ->equals("$SUBMISSION_ENTITY_ALIAS.submissionState", Submission::SUBMISSION_STATE_DRAFT)
                       ->end();

                // for draft submissions:
                if ($form->getGrantBasedSubmissionAuthorization()) {
                    if (($submissionItemActionsCurrentUserHasAReadGrantFor =
                            $this->authorizationService->getSubmissionItemActionsCurrentUserHasAReadGrantFor()) !== []) {
                        $filterTreeBuilder
                            ->inArray("$SUBMISSION_ENTITY_ALIAS.identifier", array_keys($submissionItemActionsCurrentUserHasAReadGrantFor));
                    }
                } else { // creator-based submission authorization
                    if (($currentUserIdentifier = $this->authorizationService->getUserIdentifier()) !== null) {
                        $filterTreeBuilder
                            ->equals("$SUBMISSION_ENTITY_ALIAS.creatorId", $currentUserIdentifier);
                    }
                }
                $filter = $filterTreeBuilder
                    ->end()
                    ->createFilter();

                $submissionsMayRead = $this->getSubmissions($filter, $filters, $firstResultIndex, $maxNumResults);
                $grantedSubmissionItemActionsFormLevel =
                    $this->authorizationService->getGrantedSubmissionItemActionsFormLevel($form);

                foreach ($submissionsMayRead as $submission) {
                    $submission->setGrantedActions($submission->getSubmissionState() === Submission::SUBMISSION_STATE_DRAFT ?
                        $this->authorizationService->getGrantedSubmissionItemActionsSubmissionLevel($submission,
                            $form->getGrantBasedSubmissionAuthorization() ?
                                $submissionItemActionsCurrentUserHasAReadGrantFor[$submission->getIdentifier()] : []) :
                        $grantedSubmissionItemActionsFormLevel);
                }
            } else {
                $submissionItemActionsCurrentUserHasAReadGrantFor = [];
                if ($form->getGrantBasedSubmissionAuthorization()) {
                    if (($submissionItemActionsCurrentUserHasAReadGrantFor =
                            $this->authorizationService->getSubmissionItemActionsCurrentUserHasAReadGrantFor()) === []) {
                        return [];
                    }
                    $filterTreeBuilder
                        ->inArray("$SUBMISSION_ENTITY_ALIAS.identifier", array_keys($submissionItemActionsCurrentUserHasAReadGrantFor));
                } else { // creator-based submission authorization
                    if (($currentUserIdentifier = $this->authorizationService->getUserIdentifier()) === null) {
                        return [];
                    }
                    $filterTreeBuilder
                        ->equals("$SUBMISSION_ENTITY_ALIAS.creatorId", $currentUserIdentifier);
                }

                // if submissions in submitted state mustn't be read -> require them to be drafts
                if (false === $form->isAllowedSubmissionActionWhenSubmitted(AuthorizationService::READ_SUBMISSION_ACTION)) {
                    $filterTreeBuilder
                        ->equals("$SUBMISSION_ENTITY_ALIAS.submissionState", Submission::SUBMISSION_STATE_DRAFT);
                }

                $filter = $filterTreeBuilder->createFilter();

                $submissionsMayRead = $this->getSubmissions($filter, $filters, $firstResultIndex, $maxNumResults);
                foreach ($submissionsMayRead as $submission) {
                    $submission->setGrantedActions(
                        $this->authorizationService->getGrantedSubmissionItemActionsSubmissionLevel($submission,
                            $form->getGrantBasedSubmissionAuthorization() ?
                                $submissionItemActionsCurrentUserHasAReadGrantFor[$submission->getIdentifier()] : []));
                }
            }
        } catch (FilterException $filterException) {
            throw new \RuntimeException('adding filter failed: '.$filterException->getMessage());
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
     * @param ?int $maxNumResults the maximum number of results to return, if null the number is not limited
     *
     * @return Submission[]
     */
    private function getSubmissions(Filter $filter, array $filterParameters = [],
        int $firstResultIndex = 0, ?int $maxNumResults = null): array
    {
        $SUBMISSION_ENTITY_ALIAS = self::SUBMISSION_ENTITY_ALIAS;
        $FORM_ENTITY_ALIAS = self::FORM_ENTITY_ALIAS;

        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder
            ->select($SUBMISSION_ENTITY_ALIAS)
            ->from(Submission::class, $SUBMISSION_ENTITY_ALIAS)
            ->innerJoin(Form::class, $FORM_ENTITY_ALIAS, Join::WITH,
                "$SUBMISSION_ENTITY_ALIAS.form = $FORM_ENTITY_ALIAS.identifier");

        try {
            QueryHelper::addFilter($queryBuilder, $filter);
        } catch (\Exception $exception) {
            throw new \RuntimeException('invalid get submissions filter:'.$exception->getMessage());
        }

        if (($filterParameters[self::OUTPUT_VALIDATION_FILTER] ?? null) === self::OUTPUT_VALIDATION_KEYS) {
            $this->entityManager->getConfiguration()->addCustomStringFunction('JSON_KEYS', JsonKeys::class);
            $this->entityManager->getConfiguration()->addCustomStringFunction('JSON_EXTRACT', JsonExtract::class);
            $queryBuilder
                ->andWhere("JSON_KEYS(JSON_EXTRACT($FORM_ENTITY_ALIAS.dataFeedSchema, '$.properties')) = JSON_KEYS($SUBMISSION_ENTITY_ALIAS.dataFeedElement)");
        }

        $queryBuilder->setFirstResult($firstResultIndex);
        if ($maxNumResults !== null) {
            $queryBuilder->setMaxResults($maxNumResults);
        }

        try {
            return $queryBuilder->getQuery()->getResult();
        } catch (\Exception $exception) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getMessage(),
                self::GETTING_SUBMISSION_COLLECTION_FAILED_ERROR_ID);
        }
    }

    /**
     * @param string[] $formSubmissionIdentifiers
     */
    private function doFormSubmissionCleanup(Form $form, array $formSubmissionIdentifiers): void
    {
        if ($form->getGrantBasedSubmissionAuthorization()) {
            try {
                $this->authorizationService->deregisterSubmissionsByIdentifier($formSubmissionIdentifiers);
            } catch (\Exception $e) {
                $this->logger->warning(sprintf('Failed to remove submission resources of form \'%s\' from authorization: %s',
                    $form->getIdentifier(), $e->getMessage()));
            }
        }
        foreach ($formSubmissionIdentifiers as $formSubmissionIdentifier) {
            try {
                $this->submittedFileService->removeFilesBySubmissionIdentifier($formSubmissionIdentifier);
            } catch (\Exception $e) {
                $this->logger->warning(sprintf('Failed to remove submitted files for submission \'%s\': %s',
                    $formSubmissionIdentifier, $e->getMessage()));
            }
        }
    }

    /**
     * @throws ApiError if the form is invalid
     */
    private function validateForm(Form $form): void
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

    private function validateSubmission(Submission $submission, ?Submission $previousSubmission): void
    {
        if ($submission->getForm() === null) {
            throw ApiError::withDetails(Response::HTTP_UNPROCESSABLE_ENTITY,
                'field \'form\' is required', self::REQUIRED_FIELD_MISSION_ID, ['form']);
        }

        $this->validateSubmissionState($submission, $previousSubmission);
        $this->validateSubmissionData($submission);
    }

    private function validateSubmissionState(Submission $submission, ?Submission $previousSubmission): void
    {
        if (false === $submission->getForm()->isAllowedSubmissionState($submission->getSubmissionState())) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                'The submission state \''.$submission->getSubmissionState().'\' is not allowed for the form',
                self::SUBMISSION_STATE_NOT_ALLOWED_ERROR_ID,
                [$submission->getSubmissionState()]);
        }

        $currentSubmissionState = $submission->getSubmissionState();
        $previousSubmissionState = $previousSubmission?->getSubmissionState();
        if ($currentSubmissionState === $previousSubmissionState) {
            return;
        }

        $requireUpdateFormSubmissionsPermission = false;
        $forbid = false;
        switch ($currentSubmissionState) {
            case Submission::SUBMISSION_STATE_ACCEPTED:
                // the submission is accepted
                $requireUpdateFormSubmissionsPermission = true;
                break;

            case Submission::SUBMISSION_STATE_SUBMITTED:
                switch ($previousSubmissionState) {
                    case null:
                    case Submission::SUBMISSION_STATE_DRAFT:
                        self::assertFormIsAvailable($submission->getForm());
                        break;
                    case Submission::SUBMISSION_STATE_ACCEPTED:
                        // the submission is "unaccepted"
                        $requireUpdateFormSubmissionsPermission = true;
                        break;
                }
                break;

            case Submission::SUBMISSION_STATE_DRAFT:
                if ($previousSubmissionState === Submission::SUBMISSION_STATE_ACCEPTED) {
                    // from accepted directly to draft -> not allowed
                    $forbid = true;
                } elseif ($previousSubmissionState === Submission::SUBMISSION_STATE_SUBMITTED) {
                    $forbid = true;
                    //     TODO: decide if we want to allow withdrawal of submissions:
                    //     // from submitted to draft -> only if you have submission-level delete permissions
                    //     // (e.g., you're the creator or got shared update permissions), AND the form is still available
                    //     if (false === in_array(
                    //         $this->authorizationService->getGrantedSubmissionItemActionsSubmissionLevel($submission),
                    //         [AuthorizationService::MANAGE_ACTION, AuthorizationService::DELETE_SUBMISSION_ACTION], true)
                    //     || false === self::isFormAvailable($submission->getForm())) {
                    //         $forbid = true;
                    //     }
                }
                break;
        }

        $forbid = $forbid
            || ($requireUpdateFormSubmissionsPermission
                && false === $this->authorizationService->isCurrentUserAuthorizedToUpdateFormSubmissions($submission->getForm()));

        if ($forbid) {
            throw ApiError::withDetails(Response::HTTP_FORBIDDEN, 'submissionState changed forbidden');
        }
    }

    /**
     * @throws ApiError
     */
    private function validateSubmissionData(Submission $submission): void
    {
        if ($submission->isSubmitted()) {
            $this->validateJsonData($submission);
            $this->validateFileData($submission);
        }
    }

    /**
     * @throws ApiError if the data of the submission is invalid
     */
    private function validateJsonData(Submission $submission): void
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
            $form->setDataFeedSchema(
                self::generateFormSchemaFromSubmissionData($submission));
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

    private static function generateFormSchemaFromSubmissionData(Submission $submission): string
    {
        // IDEA: Add support for non-required properties (values that are null or missing)
        // by progressively updating @autogenerated schemas as soon as we get a non-null value
        // IDEA: Improve support for arrays: Update the (initially guessed) array item type
        // as soon as we get a non-empty array.
        try {
            $formSchema = [];
            $formSchema['$comment'] = '@autogenerated_from_json_object';
            $formSchema['type'] = 'object';
            $formSchema['properties'] = [];

            $data = json_decode($submission->getDataFeedElement(), true, flags: JSON_THROW_ON_ERROR);
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

                $formSchema['properties'][$key] = $property;
            }
            if ($data !== []) {
                $formSchema['required'] = array_keys($data);
            }
            $formSchema['additionalProperties'] = true;

            self::generateFileSchemaFromSubmittedFiles($formSchema, $submission);

            return json_encode($formSchema, flags: JSON_THROW_ON_ERROR);
        } catch (\Exception $exception) {
            throw new \RuntimeException(
                'unexpected: auto-generating JSON schema from submission data failed:'.$exception->getMessage());
        }
    }

    private static function generateFileSchemaFromSubmittedFiles(array &$formSchema, Submission $submission): void
    {
        $allFiles = [];
        /** @var SubmittedFile $submittedFile */
        foreach ($submission->getSubmittedFiles() as $submittedFile) {
            Tools::pushToSubarray($allFiles, $submittedFile->getFileAttributeName(),
                self::submittedFileToFileInfo($submittedFile));
        }

        if ([] !== $allFiles) {
            $filesSchema = [];

            foreach ($allFiles as $fileAttributeName => $fileInfos) {
                $fileAttributeSchema = [
                    self::FILE_SCHEMA_MIN_NUMBER_ATTRIBUTE => 0, // DECISION: don't require the file attribute
                    self::FILE_SCHEMA_MAX_NUMBER_ATTRIBUTE => count($fileInfos) + 1,
                    self::FILE_SCHEMA_ALLOWED_MIME_TYPES_ATTRIBUTE => array_unique(array_map(
                        function (array $fileInfo): string {
                            return $fileInfo['mimeType'];
                        }, $fileInfos)),
                    self::FILE_SCHEMA_MAX_SIZE_MB_ATTRIBUTE => max(self::FILE_SCHEMA_MAX_SIZE_MB_DEFAULT,
                        ceil((float) max(array_map(
                            function (array $fileInfo): int {
                                return $fileInfo['size'];
                            }, $fileInfos)) / self::BYTES_PER_MB)),
                ];
                $filesSchema[$fileAttributeName] = $fileAttributeSchema;
            }
            $formSchema[self::FORM_SCHEMA_FILES_ATTRIBUTE] = $filesSchema;
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

    private static function assertFormIsAvailable(Form $form): void
    {
        if (self::isFormAvailable($form) === false) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                'The specified form is currently not available', self::SUBMISSION_FORM_CURRENTLY_NOT_AVAILABLE_ERROR_ID);
        }
    }

    private static function isFormAvailable(Form $form): bool
    {
        try {
            $dateTimeNowUtc = new \DateTime('now', new \DateTimeZone('UTC'));
        } catch (\Exception $exception) {
            throw new \RuntimeException($exception->getMessage());
        }

        return ($form->getAvailabilityStarts() === null || $dateTimeNowUtc >= $form->getAvailabilityStarts())
            && ($form->getAvailabilityEnds() === null || $dateTimeNowUtc <= $form->getAvailabilityEnds());
    }

    /**
     * @throws ApiError
     */
    private function validateFileData(Submission $submission): void
    {
        try {
            $dataSchemaObject = json_decode($submission->getForm()->getDataFeedSchema(),
                true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $jsonException) {
            throw new \RuntimeException('Unexpected: dataFeedSchema is not valid JSON: '.$jsonException->getMessage());
        }

        $allFiles = [];
        $this->submittedFileService->setSubmittedFilesDetails($submission);

        /** @var SubmittedFile $submittedFile */
        foreach ($submission->getSubmittedFiles() as $submittedFile) {
            Tools::pushToSubarray($allFiles, $submittedFile->getFileAttributeName(),
                self::submittedFileToFileInfo($submittedFile));
        }

        $filesSchema = $dataSchemaObject[self::FORM_SCHEMA_FILES_ATTRIBUTE] ?? [];
        $additionalFilesAllowed = filter_var(
            $dataSchemaObject[self::FILE_SCHEMA_ADDITIONAL_FILES_ATTRIBUTE] ?? false,
            FILTER_VALIDATE_BOOLEAN) === true;

        foreach ($allFiles as $fileAttributeName => $fileInfos) {
            $fileAttributeSchema = $filesSchema[$fileAttributeName] ?? null;
            if ($fileAttributeSchema === null) {
                if ($additionalFilesAllowed) {
                    // for additional files we use a default schema, allowing a maximum of 2 pdf files per attribute
                    // (should be used for testing/development purposes only):
                    $fileAttributeSchema = [
                        self::FILE_SCHEMA_MAX_NUMBER_ATTRIBUTE => 2,
                        self::FILE_SCHEMA_ALLOWED_MIME_TYPES_ATTRIBUTE => [
                            'application/pdf',
                        ],
                    ];
                } else {
                    throw ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                        'file attribute is not defined in the form schema',
                        self::SUBMISSION_SUBMITTED_FILES_INVALID_SCHEMA_ERROR_ID,
                        [$fileAttributeName]);
                }
            }
            unset($filesSchema[$fileAttributeName]); // remove already handled attributes

            $minNumber = $fileAttributeSchema[self::FILE_SCHEMA_MIN_NUMBER_ATTRIBUTE] ?? self::FILE_SCHEMA_MIN_NUMBER_DEFAULT;
            $maxNumber = $fileAttributeSchema[self::FILE_SCHEMA_MAX_NUMBER_ATTRIBUTE] ?? self::FILE_SCHEMA_MAX_NUMBER_DEFAULT;
            if (count($fileInfos) < $minNumber) {
                throw ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                    'number of uploaded files is below the minimum for this file attribute',
                    self::SUBMISSION_SUBMITTED_FILES_INVALID_SCHEMA_ERROR_ID,
                    [$fileAttributeName]);
            }
            if (count($fileInfos) > $maxNumber) {
                throw ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                    'number of uploaded files is above the maximum for this file attribute',
                    self::SUBMISSION_SUBMITTED_FILES_INVALID_SCHEMA_ERROR_ID,
                    [$fileAttributeName]);
            }
            $maxSizeBytes = self::BYTES_PER_MB *
                ($fileAttributeSchema[self::FILE_SCHEMA_MAX_SIZE_MB_ATTRIBUTE] ?? self::FILE_SCHEMA_MAX_SIZE_MB_DEFAULT);
            $allowedMimeTypes = $fileAttributeSchema[self::FILE_SCHEMA_ALLOWED_MIME_TYPES_ATTRIBUTE] ??
                self::FILE_SCHEMA_ALLOWED_MIME_TYPES_DEFAULT;

            foreach ($fileInfos as $fileInfo) {
                if ($maxSizeBytes < $fileInfo['size']) {
                    throw ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                        'file size exceeds allowed limit for this file attribute',
                        self::SUBMISSION_SUBMITTED_FILES_INVALID_SCHEMA_ERROR_ID,
                        [$fileAttributeName]);
                }
                if (false === in_array($fileInfo['mimeType'], $allowedMimeTypes, true)) {
                    throw ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                        'mime type is not allowed for this file attribute: '.$fileInfo['mimeType'],
                        self::SUBMISSION_SUBMITTED_FILES_INVALID_SCHEMA_ERROR_ID,
                        [$fileAttributeName, $fileInfo['mimeType']]);
                }
            }
        }

        // check remaining file attributes (those who are not present in the submitted files)
        // -> they must have minimum number of 0 otherwise it's a schema violation
        foreach ($filesSchema as $fileAttributeName => $fileAttributeSchema) {
            if (0 !==
                ($fileAttributeSchema[self::FILE_SCHEMA_MIN_NUMBER_ATTRIBUTE] ?? self::FILE_SCHEMA_MIN_NUMBER_DEFAULT)) {
                throw ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                    'required file attribute is missing',
                    self::SUBMISSION_SUBMITTED_FILES_INVALID_SCHEMA_ERROR_ID, [$fileAttributeName]);
            }
        }
    }

    private static function submittedFileToFileInfo(SubmittedFile $submittedFile): array
    {
        return [
            'size' => $submittedFile->getFileSize(),
            'mimeType' => $submittedFile->getMimeType(),
        ];
    }
}
