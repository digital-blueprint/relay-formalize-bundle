<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\DataPersister;

use ApiPlatform\Core\DataPersister\DataPersisterInterface;
use Dbp\Relay\FormalizeBundle\Entity\Submission;
use Dbp\Relay\FormalizeBundle\Service\FormsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class SubmissionDataPersister extends AbstractController implements DataPersisterInterface
{
    private $api;

    public function __construct(FormsService $api)
    {
        $this->api = $api;
    }

    public function supports($data): bool
    {
        return $data instanceof Submission;
    }

    /**
     * @param mixed $data
     *
     * @return Submission
     */
    public function persist($data)
    {
        $this->denyAccessUnlessGranted('ROLE_SCOPE_FORMALIZE-POST');

        $submission = $data;
        assert($submission instanceof Submission);

        return $this->api->createSubmission($submission);
    }

    public function remove($data)
    {
    }
}
