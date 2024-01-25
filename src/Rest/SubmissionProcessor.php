<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Rest;

use Dbp\Relay\CoreBundle\Rest\AbstractDataProcessor;
use Dbp\Relay\FormalizeBundle\Entity\Submission;
use Dbp\Relay\FormalizeBundle\Service\FormalizeService;

class SubmissionProcessor extends AbstractDataProcessor
{
    /**
     * @var FormalizeService
     */
    private $formalizeService;

    public function __construct(FormalizeService $formalizeService)
    {
        $this->formalizeService = $formalizeService;
    }

    protected function addItem($data, array $filters): Submission
    {
        $submission = $data;
        assert($submission instanceof Submission);

        return $this->formalizeService->addSubmission($submission);
    }

    protected function updateItem($identifier, $data, $previousData, array $filters)
    {
        $submission = $data;
        assert($submission instanceof Submission);

        return $this->formalizeService->updateSubmission($submission);
    }

    protected function removeItem($identifier, $data, array $filters): void
    {
        $submission = $data;
        assert($submission instanceof Submission);

        $this->formalizeService->removeSubmission($submission);
    }

    protected function isUserGrantedOperationAccess(int $operation): bool
    {
        return $this->isAuthenticated()
            && ($this->getUserAttribute('SCOPE_FORMALIZE_POST')
                || $this->getUserAttribute('ROLE_DEVELOPER'));
    }
}
