<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Service;

use Dbp\Relay\FormalizeBundle\Entity\Submission;
use Dbp\Relay\FormalizeBundle\Entity\SubmissionPersistence;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

class FormsService
{
    private $submissions;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    public function __construct(MyCustomService $service, ManagerRegistry $managerRegistry)
    {
        $manager = $managerRegistry->getManager('dbp_relay_formalize_bundle');
        assert($manager instanceof EntityManagerInterface);
        $this->em = $manager;

        // Make phpstan happy
        $service = $service;

        $this->submissions = [];
        $submission1 = new Submission();

        $submission1->setIdentifier((string) Uuid::v4());
        $submission1->setDataFeedElement('{"name":"John Doe"}');

        $submission2 = new Submission();
        $submission2->setIdentifier((string) Uuid::v4());
        $submission2->setDataFeedElement('{"name":"Jane Doe"}');

        $this->submissions[] = $submission1;
        $this->submissions[] = $submission2;
    }

    public function getSubmissionById(string $identifier): ?Submission
    {
        foreach ($this->submissions as $submission) {
            if ($submission->getIdentifier() === $identifier) {
                return $submission;
            }
        }

        return null;
    }

    public function getSubmissions(): array
    {
        return $this->submissions;
    }

    public function createSubmission(Submission $submission): Submission
    {
        $submissionPersistence = SubmissionPersistence::fromSubmission($submission);
        $submissionPersistence->setIdentifier((string) Uuid::v4());
        $submissionPersistence->setDateCreated(new \DateTime('now'));

        try {
            $this->em->persist($submissionPersistence);
            $this->em->flush();
        } catch (\Exception $e) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Submission could not be created!', 'formalize:form-data-not-created', ['message' => $e->getMessage()]);
        }

        return Submission::fromSubmissionPersistence($submissionPersistence);
    }
}
