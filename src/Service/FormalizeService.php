<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Service;

use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\Helpers\Tools;
use Dbp\Relay\CoreBundle\Rest\Query\Pagination\Pagination;
use Dbp\Relay\FormalizeBundle\Authorization\AuthorizationService;
use Dbp\Relay\FormalizeBundle\Entity\Form;
use Dbp\Relay\FormalizeBundle\Entity\Submission;
use Dbp\Relay\FormalizeBundle\Event\CreateSubmissionPostEvent;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use JsonSchema\Constraints\Constraint;
use JsonSchema\Exception\InvalidSchemaException;
use JsonSchema\Exception\ValidationException;
use JsonSchema\Validator;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

class FormalizeService
{
    private const FORM_NOT_FOUND_ERROR_ID = 'formalize:form-with-id-not-found';
    private const REQUIRED_FIELD_MISSION_ID = 'formalize:required-field-missing';
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
    private const SUBMISSION_DATA_FEED_ELEMENT_INVALID_JSON_KEYS_ERROR_ID = 'formalize:submission-invalid-json-keys';
    private const SUBMISSION_DATA_FEED_ELEMENT_INVALID_SCHEMA_ERROR_ID = 'formalize:submission-data-feed-invalid-schema';
    private const FORM_INVALID_DATA_FEED_SCHEMA_ERROR_ID = 'formalize:form-invalid-data-feed-schema';
    private const SUBMISSION_FORM_CURRENTLY_NOT_AVAILABLE_ERROR_ID = 'formalize:submission-form-currently-not-available';

    /** @var EntityManagerInterface */
    private $entityManager;

    /** @var EventDispatcherInterface */
    private $eventDispatcher;

    /** @var AuthorizationService */
    private $authorizationService;

    public function __construct(EntityManagerInterface $entityManager, EventDispatcherInterface $eventDispatcher,
        AuthorizationService $authorizationService)
    {
        $this->entityManager = $entityManager;
        $this->eventDispatcher = $eventDispatcher;
        $this->authorizationService = $authorizationService;
    }

    /**
     * @throws Exception
     */
    public function checkConnection(): void
    {
        $this->entityManager->getConnection()->connect();
    }

    /**
     * @return Submission[]
     */
    public function getSubmissionsByForm(string $formIdentifier, int $currentPageNumber, int $maxNumItemsPerPage): array
    {
        $ENTITY_ALIAS = 's';

        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select($ENTITY_ALIAS)
            ->from(Submission::class, $ENTITY_ALIAS);

        return $queryBuilder
            ->where($queryBuilder->expr()->eq($ENTITY_ALIAS.'.form', '?1'))
            ->setParameter(1, $formIdentifier)
            ->getQuery()
            ->setFirstResult(Pagination::getFirstItemIndex($currentPageNumber, $maxNumItemsPerPage))
            ->setMaxResults($maxNumItemsPerPage)
            ->getResult();
    }

    public function getSubmissionByIdentifier(string $identifier): Submission
    {
        $submission = $this->entityManager
            ->getRepository(Submission::class)
            ->find($identifier);

        if ($submission === null) {
            $apiError = ApiError::withDetails(Response::HTTP_NOT_FOUND, 'Submission was not found!',
                self::SUBMISSION_NOT_FOUND_ERROR_ID, [$identifier]);
            throw $apiError;
        }

        return $submission;
    }

    public function tryGetOneSubmissionByFormId(string $form): ?Submission
    {
        return $this->entityManager
            ->getRepository(Submission::class)
            ->findOneBy(['form' => $form]);
    }

    /**
     * @throws ApiError
     */
    public function addSubmission(Submission $submission): Submission
    {
        if ($submission->getDataFeedElement() === null) {
            $apiError = ApiError::withDetails(Response::HTTP_UNPROCESSABLE_ENTITY,
                'field \'dataFeedElement\' is required', self::REQUIRED_FIELD_MISSION_ID, ['dataFeedElement']);
            throw $apiError;
        }

        if ($submission->getForm() === null) {
            $apiError = ApiError::withDetails(Response::HTTP_UNPROCESSABLE_ENTITY,
                'field \'form\' is required', self::REQUIRED_FIELD_MISSION_ID, ['form']);
            throw $apiError;
        }

        $this->validateSubmission($submission);

        $submission->setIdentifier((string) Uuid::v4());
        $submission->setDateCreated(new \DateTime('now'));
        $submission->setCreatorId($this->authorizationService->getUserIdentifier());

        try {
            $this->entityManager->persist($submission);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            $apiError = ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR,
                'Submission could not be created!', self::ADDING_SUBMISSION_FAILED_ERROR_ID, ['message' => $e->getMessage()]);
            throw $apiError;
        }

        $postEvent = new CreateSubmissionPostEvent($submission);
        $this->eventDispatcher->dispatch($postEvent);

        return $submission;
    }

    public function updateSubmission(Submission $submission): Submission
    {
        $this->validateSubmission($submission);

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
        } catch (\Exception $e) {
            $apiError = ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Submission could not be removed!',
                self::REMOVING_SUBMISSION_FAILED_ERROR_ID, ['message' => $e->getMessage()]);
            throw $apiError;
        }
    }

    /**
     * @throws ApiError
     */
    public function addForm(Form $form): Form
    {
        if ($form->getName() === null) {
            $apiError = ApiError::withDetails(Response::HTTP_UNPROCESSABLE_ENTITY,
                'field \'name\' is required', self::REQUIRED_FIELD_MISSION_ID, ['name']);
            throw $apiError;
        }

        $form->setIdentifier((string) Uuid::v4());
        $form->setDateCreated(new \DateTime('now'));
        $form->setCreatorId($this->authorizationService->getUserIdentifier());

        $this->validateForm($form);

        try {
            $this->entityManager->persist($form);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            $apiError = ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Form could not be created!',
                self::ADDING_FORM_FAILED_ERROR_ID, ['message' => $e->getMessage()]);
            throw $apiError;
        }

        return $form;
    }

    /**
     * @throws ApiError
     */
    public function removeForm(Form $form): void
    {
        try {
            $ENTITY_ALIAS = 's';
            $queryBuilder = $this->entityManager->createQueryBuilder();
            $queryBuilder->delete(Submission::class, $ENTITY_ALIAS)
                ->where($queryBuilder->expr()->eq($ENTITY_ALIAS.'.form', '?1'))
                ->setParameter(1, $form->getIdentifier())
                ->getQuery()
                ->execute();
            $this->entityManager->remove($form);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            $apiError = ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR,
                'Form could not be removed!', self::REMOVING_FORM_FAILED_ERROR_ID,
                ['message' => $e->getMessage()]);
            throw $apiError;
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
            $apiError = ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR,
                'Form could not be updated!', self::UPDATING_FORM_FAILED_ERROR_ID,
                ['message' => $e->getMessage()]);
            throw $apiError;
        }

        return $form;
    }

    public function removeAllFormSubmissions(string $formIdentifier)
    {
        $ENTITY_ALIAS = 's';

        try {
            $queryBuilder = $this->entityManager->createQueryBuilder();
            $queryBuilder->delete(Submission::class, $ENTITY_ALIAS)
                ->where($queryBuilder->expr()->eq($ENTITY_ALIAS.'.form', '?1'))
                ->setParameter(1, $formIdentifier)
                ->getQuery()
                ->execute();
        } catch (\Exception $e) {
            $apiError = ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR,
                'Form submissions could not be removed!', self::REMOVING_FORM_SUBMISSIONS_FAILED,
                ['message' => $e->getMessage()]);
            throw $apiError;
        }
    }

    /**
     * @throws ApiError
     */
    public function getForm(string $identifier): Form
    {
        $form = $this->entityManager->getRepository(Form::class)->findOneBy(['identifier' => $identifier]);
        if ($form === null) {
            $apiError = ApiError::withDetails(Response::HTTP_NOT_FOUND, 'Form could not be found',
                self::FORM_NOT_FOUND_ERROR_ID, [$identifier]);
            throw $apiError;
        }

        return $form;
    }

    /**
     * @throws ApiError
     */
    public function getForms(int $currentPageNumber, int $maxNumItemsPerPage): array
    {
        $ENTITY_ALIAS = 'f';

        return $this->entityManager->createQueryBuilder()
            ->select($ENTITY_ALIAS)
            ->from(Form::class, $ENTITY_ALIAS)
            ->getQuery()
            ->setFirstResult(Pagination::getFirstItemIndex($currentPageNumber, $maxNumItemsPerPage))
            ->setMaxResults($maxNumItemsPerPage)
            ->getResult();
    }

    /**
     * @throws ApiError if the form data is invalid
     */
    private function validateForm(Form $form): void
    {
        $form->validateUsersOptions();

        $dataFeedSchema = $form->getDataFeedSchema();
        if ($dataFeedSchema !== null) {
            // create a dummy JSON value object to validate the JSON schema against
            try {
                $dummyValue = Tools::decodeJSON('{}', false);
            } catch (\JsonException $exception) {
                $apiError = ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR,
                    'unexpected: decoding dummy JSON failed', self::UNEXPECTED_ERROR_ID);
                throw $apiError;
            }

            // only validate the schema, ignore validation errors caused by the dummy JSON value object not complying with the schema
            try {
                $dataFeedSchemaObject = Tools::decodeJSON($dataFeedSchema);
                $jsonSchemaValidator = new Validator();
                $jsonSchemaValidator->validate(
                    $dummyValue, $dataFeedSchemaObject,
                    Constraint::CHECK_MODE_VALIDATE_SCHEMA | Constraint::CHECK_MODE_EXCEPTIONS);
            } catch (\JsonException|InvalidSchemaException $exception) {
                $apiError = ApiError::withDetails(Response::HTTP_UNPROCESSABLE_ENTITY,
                    '\'dataFeedSchema\' is not a valid JSON schema', self::FORM_INVALID_DATA_FEED_SCHEMA_ERROR_ID,
                    $exception instanceof InvalidSchemaException && $exception->getPrevious() !== null ?
                        [$exception->getPrevious()->getMessage()] : []);
                throw $apiError;
            } catch (ValidationException $validationException) {
                // ignore
            }
        }
    }

    /**
     * @throws ApiError if the data of the submission is invalid
     */
    private function validateSubmission(Submission $submission): void
    {
        $form = $submission->getForm();

        try {
            $dateTimeNowUtc = new \DateTime('now', new \DateTimeZone('UTC'));
        } catch (\Exception $exception) {
            throw new \RuntimeException($exception->getMessage());
        }
        if (($form->getAvailabilityStarts() !== null && $dateTimeNowUtc < $form->getAvailabilityStarts())
            || ($form->getAvailabilityEnds() !== null && $dateTimeNowUtc > $form->getAvailabilityEnds())) {
            $apiError = ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                'The specified form is currently not available', self::SUBMISSION_FORM_CURRENTLY_NOT_AVAILABLE_ERROR_ID);
            throw $apiError;
        }

        $dataFeedSchema = $form->getDataFeedSchema();
        $validateAgainstJsonSchema = $dataFeedSchema !== null;

        try {
            // NOTE: JSON Validator requires the data feed element to be ob type object,
            // array key comparison needs an associative array
            $dataFeedElement = Tools::decodeJSON($submission->getDataFeedElement(), !$validateAgainstJsonSchema);
        } catch (\JsonException $e) {
            $apiError = ApiError::withDetails(Response::HTTP_UNPROCESSABLE_ENTITY,
                'The dataFeedElement doesn\'t contain valid json!',
                self::SUBMISSION_DATA_FEED_ELEMENT_INVALID_JSON_ERROR_ID);
            throw $apiError;
        }

        if ($validateAgainstJsonSchema) {
            try {
                $dataFeedSchemaObject = Tools::decodeJSON($dataFeedSchema);
                $jsonSchemaValidator = new Validator();
                if ($jsonSchemaValidator->validate(
                    $dataFeedElement, (object) $dataFeedSchemaObject) !== Validator::ERROR_NONE) {
                    $apiError = ApiError::withDetails(Response::HTTP_UNPROCESSABLE_ENTITY,
                        'The dataFeedElement doesn\'t comply with the JSON schema defined in the form!',
                        self::SUBMISSION_DATA_FEED_ELEMENT_INVALID_SCHEMA_ERROR_ID,
                        array_map(function ($error) {
                            return ($error['property'] !== null ? $error['property'].': ' : '').($error['message'] ?? '');
                        },
                            $jsonSchemaValidator->getErrors()));
                    throw $apiError;
                }
            } catch (\JsonException $e) {
                // this should never happen since the the validity of the schema is checked on form creation
                throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, $e->getMessage());
            }
        } else { // validate against prior submissions
            $formId = $submission->getForm()->getIdentifier();
            $priorSubmission = $this->tryGetOneSubmissionByFormId($formId);
            if ($priorSubmission === null) {
                return; // No prior submissions, so it's okay to create a new scheme
            }

            try {
                $priorDataFeedElement = json_decode($priorSubmission->getDataFeedElement(), true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                $apiError = ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR,
                    sprintf('The prior submssion \'%s\' for the form \'%s\' does not contain valid JSON!', $priorSubmission->getIdentifier(), $formId),
                    self::UNEXPECTED_ERROR_ID, [$priorSubmission->getIdentifier(), $formId]);
                throw $apiError;
            }

            // If there is a diff between old and new scheme throw an error
            if (count(array_diff_key($priorDataFeedElement, $dataFeedElement)) > 0) {
                $apiError = ApiError::withDetails(Response::HTTP_UNPROCESSABLE_ENTITY,
                    'The dataFeedElement doesn\'t match with the pevious submissions of the form: \''.$formId.'\' (the keys must correspond to scheme: \''.implode("', '", array_keys($priorDataFeedElement)).'\')',
                    self::SUBMISSION_DATA_FEED_ELEMENT_INVALID_JSON_KEYS_ERROR_ID);
                throw $apiError;
            }
        }
    }
}
