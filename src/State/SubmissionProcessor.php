<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Dbp\Relay\FormalizeBundle\Entity\Submission;
use Dbp\Relay\FormalizeBundle\Service\FormalizeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class SubmissionProcessor extends AbstractController implements ProcessorInterface
{
    /**
     * @var FormalizeService
     */
    private $api;

    public function __construct(FormalizeService $api)
    {
        $this->api = $api;
    }

    /**
     * @return Submission
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $this->denyAccessUnlessGranted('ROLE_SCOPE_FORMALIZE-POST');

        $submission = $data;
        assert($submission instanceof Submission);

        return $this->api->createSubmission($submission);
    }
}
