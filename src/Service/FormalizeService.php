<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Service;

use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\Helpers\Tools;
use Dbp\Relay\CoreBundle\Rest\Query\Pagination\Pagination;
use Dbp\Relay\FormalizeBundle\Entity\Form;
use Dbp\Relay\FormalizeBundle\Entity\Submission;
use Dbp\Relay\FormalizeBundle\Event\CreateSubmissionPostEvent;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

class FormalizeService
{
    private const FORM_NOT_FOUND_ERROR_ID = 'formalize:form-with-id-not-found';
    private const ADDING_FORM_FAILED_ERROR_ID = 'formalize:form-not-created';
    private const REMOVING_SUBMISSION_FAILED_ERROR_ID = 'formalize:submission-not-removed';
    private const ADDING_SUBMISSION_FAILED_ERROR_ID = 'formalize:submission-not-created';
    private const UPDATING_SUBMISSION_FAILED_ERROR_ID = 'formalize:submission-not-updated';
    private const SUBMISSION_NOT_FOUND_ERROR_ID = 'formalize:submission-not-found';
    private const REMOVING_FORM_FAILED_ERROR_ID = 'formalize:form-not-removed';
    private const UPDATING_FORM_FAILED_ERROR_ID = 'formalize:updating-form-failed';
    private const UNEXPECTED_ERROR_ID = 'formalize:unexpected-error';
    private const SUBMISSION_INVALID_JSON = 'formalize:submission-invalid-json';

    private const FORM_IDENTIFIER_FILTER = 'formIdentifier';

    /** @var EntityManagerInterface */
    private $entityManager;

    /** @var EventDispatcherInterface */
    private $eventDispatcher;

    public function __construct(EntityManagerInterface $entityManager, EventDispatcherInterface $eventDispatcher)
    {
        $this->entityManager = $entityManager;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @throws Exception
     */
    public function checkConnection()
    {
        $this->entityManager->getConnection()->connect();
    }

    /**
     * @return Submission[]
     */
    public function getSubmissions(int $currentPageNumber, int $maxNumItemsPerPage, array $filters = []): array
    {
        $ENTITY_ALIAS = 's';

        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select($ENTITY_ALIAS)
            ->from(Submission::class, $ENTITY_ALIAS);

        $formId = $filters[self::FORM_IDENTIFIER_FILTER] ?? null;
        if (!Tools::isNullOrEmpty($formId)) {
            $queryBuilder
                ->where($queryBuilder->expr()->eq($ENTITY_ALIAS.'.form', '?1'))
                ->setParameter(1, $formId);
        }

        return $queryBuilder->getQuery()
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
            throw ApiError::withDetails(Response::HTTP_NOT_FOUND, 'Submission was not found!', self::SUBMISSION_NOT_FOUND_ERROR_ID, [$identifier]);
        }

        return $submission;
    }

    public function getSubmissionsByForm(string $formId): array
    {
        return $this->entityManager
            ->getRepository(Submission::class)
            ->findBy(['form' => $formId]);
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
        $this->validateDataFeedElement($submission);

        $submission->setIdentifier((string) Uuid::v4());
        $submission->setDateCreated(new \DateTime('now'));

        try {
            $this->entityManager->persist($submission);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Submission could not be created!', self::ADDING_SUBMISSION_FAILED_ERROR_ID, ['message' => $e->getMessage()]);
        }

        $postEvent = new CreateSubmissionPostEvent($submission);
        $this->eventDispatcher->dispatch($postEvent);

        return $submission;
    }

    public function updateSubmission(Submission $submission): Submission
    {
        $this->validateDataFeedElement($submission);

        try {
            $this->entityManager->persist($submission);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Submission could not be updated!', self::UPDATING_SUBMISSION_FAILED_ERROR_ID, ['message' => $e->getMessage()]);
        }

        return $submission;
    }

    public function removeSubmission(Submission $submission): void
    {
        try {
            $this->entityManager->remove($submission);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Submission could not be removed!', self::REMOVING_SUBMISSION_FAILED_ERROR_ID, ['message' => $e->getMessage()]);
        }
    }

    /**
     * @throws ApiError
     */
    public function addForm(Form $form): Form
    {
        $form->setIdentifier((string) Uuid::v4());
        $form->setDateCreated(new \DateTime('now'));

        try {
            $this->entityManager->persist($form);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Form could not be created!', self::ADDING_FORM_FAILED_ERROR_ID, ['message' => $e->getMessage()]);
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
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Form could not be removed!', self::REMOVING_FORM_FAILED_ERROR_ID, ['message' => $e->getMessage()]);
        }
    }

    /**
     * @throws ApiError
     */
    public function updateForm(Form $form): Form
    {
        try {
            $this->entityManager->persist($form);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Form could not be updated!', self::UPDATING_FORM_FAILED_ERROR_ID, ['message' => $e->getMessage()]);
        }

        return $form;
    }

    /**
     * @throws ApiError
     */
    public function getForm(string $identifier): Form
    {
        $form = $this->entityManager->getRepository(Form::class)->findOneBy(['identifier' => $identifier]);
        if ($form === null) {
            throw ApiError::withDetails(Response::HTTP_NOT_FOUND, 'Form could not be found', self::FORM_NOT_FOUND_ERROR_ID, [$identifier]);
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
     * @throws ApiError if the data feed element is invalid
     */
    public function validateDataFeedElement(Submission $submission): void
    {
        // from blob:
//        $validator = new Validator();
//        $metadataDecoded = (object) json_decode($additionalMetadata);
//
//        // check if given additionalMetadata json has the same keys like the defined additionalType
//        if ($additionalType && $additionalMetadata && $validator->validate($metadataDecoded, (object) json_decode($bucket->getAdditionalTypes()[$additionalType])) !== 0) {
//            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST, 'additionalType mismatch', 'blob:create-file-additional-type-mismatch');
//        }


        try {
            $dataFeedElement = json_decode($submission->getDataFeedElement(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw ApiError::withDetails(Response::HTTP_UNPROCESSABLE_ENTITY, 'The dataFeedElement doesn\'t contain valid json!', self::SUBMISSION_INVALID_JSON);
        }

        $formId = $submission->getForm()->getIdentifier();
        $priorSubmission = $this->tryGetOneSubmissionByFormId($formId);
        if ($priorSubmission === null) {
            return; // No prior submissions, so it's okay to create a new scheme
        }

        try {
            $priorDataFeedElement = json_decode($priorSubmission->getDataFeedElement(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, sprintf('The prior submssion \'%s\' for the form \'%s\' does not contain valid JSON!', $priorSubmission->getIdentifier(), $formId), self::UNEXPECTED_ERROR_ID, [$priorSubmission->getIdentifier(), $formId]);
        }

        // If there is a diff between old and new scheme throw an error
        if (count(array_diff_key($priorDataFeedElement, $dataFeedElement)) > 0) {
            throw ApiError::withDetails(Response::HTTP_UNPROCESSABLE_ENTITY, 'The dataFeedElement doesn\'t match with the pevious submissions of the form: \''.$formId.'\' (the keys must correspond to scheme: \''.implode("', '", array_keys($priorDataFeedElement)).'\')', 'formalize:submission-invalid-json-keys');
        }
    }
}
