<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Service;

use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\FormalizeBundle\Entity\Submission;
use Dbp\Relay\FormalizeBundle\Entity\SubmissionPersistence;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

class FormsService
{
    private $submissions;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    public function __construct(ManagerRegistry $managerRegistry)
    {
        $manager = $managerRegistry->getManager('dbp_relay_formalize_bundle');
        assert($manager instanceof EntityManagerInterface);
        $this->em = $manager;

        $this->submissions = [];
        $submission1 = new Submission();

        $submission1->setIdentifier((string) Uuid::v4());
        $submission1->setDataFeedElement('{"name":"John Doe"}');
        $submission1->setDateCreated(\DateTime::createFromFormat('Y-m-d H:i:s', '2020-01-01 00:00:00'));

        $submission2 = new Submission();
        $submission2->setIdentifier((string) Uuid::v4());
        $submission2->setDateCreated(\DateTime::createFromFormat('Y-m-d H:i:s', '2022-02-02 00:00:00'));

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
        $submission->setIdentifier((string) Uuid::v4());
        $submission->setDateCreated(new \DateTime('now'));

        $submissionPersistence = SubmissionPersistence::fromSubmission($submission);

        try {
            $this->em->persist($submissionPersistence);
            $this->em->flush();
        } catch (\Exception $e) {
            // TODO: Fix and implement
//            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Submission could not be created!', 'formalize:form-data-not-created', ['message' => $e->getMessage()]);
        }

        return Submission::fromSubmissionPersistence($submissionPersistence);
    }
}
