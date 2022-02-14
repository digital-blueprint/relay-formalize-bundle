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
    /**
     * @var EntityManagerInterface
     */
    private $em;

    public function __construct(ManagerRegistry $managerRegistry)
    {
        $manager = $managerRegistry->getManager('dbp_relay_formalize_bundle');
        assert($manager instanceof EntityManagerInterface);
        $this->em = $manager;
    }

    public function getSubmissions(): array
    {
        $permitPersistences = $this->em
            ->getRepository(SubmissionPersistence::class)
            ->findAll();

        return Submission::fromSubmissionPersistences($permitPersistences);
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
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Submission could not be created!', 'formalize:form-data-not-created', ['message' => $e->getMessage()]);
        }

        return Submission::fromSubmissionPersistence($submissionPersistence);
    }
}
