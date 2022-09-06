<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Service;

use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\FormalizeBundle\Entity\Submission;
use Dbp\Relay\FormalizeBundle\Entity\SubmissionPersistence;
use Dbp\Relay\FormalizeBundle\Event\CreateSubmissionPostEvent;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

class FormalizeService
{
    /**
     * @var EntityManagerInterface
     */
    private $em;

    /** @var EventDispatcherInterface */
    private $dispatcher;

    public function __construct(ManagerRegistry $managerRegistry, EventDispatcherInterface $dispatcher)
    {
        $manager = $managerRegistry->getManager('dbp_relay_formalize_bundle');
        assert($manager instanceof EntityManagerInterface);
        $this->em = $manager;
        $this->dispatcher = $dispatcher;
    }

    public function checkConnection()
    {
        $this->em->getConnection()->connect();
    }

    public function getSubmissions(): array
    {
        $submissionPersistences = $this->em
            ->getRepository(SubmissionPersistence::class)
            ->findAll();

        return Submission::fromSubmissionPersistences($submissionPersistences);
    }

    public function getSubmissionByIdentifier(string $identifier): ?Submission
    {
        $submissionPersistence = $this->em
            ->getRepository(SubmissionPersistence::class)
            ->find($identifier);

        if (!$submissionPersistence) {
            throw ApiError::withDetails(Response::HTTP_NOT_FOUND, 'Submission was not found!', 'formalize:submission-not-found');
        }

        return Submission::fromSubmissionPersistence($submissionPersistence);
    }

    public function getSubmissionByForm(string $form): array
    {
        $submissionPersistences = $this->em
            ->getRepository(SubmissionPersistence::class)
            ->findBy(array('form' => $form));

        if (!$submissionPersistences) {
            throw ApiError::withDetails(Response::HTTP_NOT_FOUND, 'Submission was not found!', 'formalize:submission-not-found');
        }

        return Submission::fromSubmissionPersistences($submissionPersistences);
    }

    public function getOneSubmissionByForm(string $form): ?Submission
    {
        $submissionPersistence = $this->em
            ->getRepository(SubmissionPersistence::class)
            ->findOneBy(array('form' => $form));

        if (!$submissionPersistence) {
            throw ApiError::withDetails(Response::HTTP_NOT_FOUND, 'Submission was not found!', 'formalize:submission-not-found');
        }

        return Submission::fromSubmissionPersistence($submissionPersistence);
    }

    public function createSubmission(Submission $submission): Submission
    {
        // Check if json is valid
        try {
            $submission->getDataFeedElementDecoded();
        } catch (\JsonException $e) {
            throw ApiError::withDetails(Response::HTTP_UNPROCESSABLE_ENTITY, 'The dataFeedElement doesn\'t contain valid json!', 'formalize:submission-invalid-json');
        }

        // Check if key from json are valid
        $submission->compareDataFeedElementKeys($this);

        $submission->setIdentifier((string) Uuid::v4());
        $submission->setDateCreated(new \DateTime('now'));

        $submissionPersistence = SubmissionPersistence::fromSubmission($submission);

        try {
            $this->em->persist($submissionPersistence);
            $this->em->flush();
        } catch (\Exception $e) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Submission could not be created!', 'formalize:submission-not-created', ['message' => $e->getMessage()]);
        }

        $submission = Submission::fromSubmissionPersistence($submissionPersistence);

        $postEvent = new CreateSubmissionPostEvent($submission);
        $this->dispatcher->dispatch($postEvent, CreateSubmissionPostEvent::NAME);

        return $submission;
    }
}
