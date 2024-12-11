<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Rest;

use Dbp\Relay\CoreBundle\Rest\AbstractDataProcessor;
use Dbp\Relay\FormalizeBundle\Authorization\AuthorizationService;
use Dbp\Relay\FormalizeBundle\Entity\Submission;
use Dbp\Relay\FormalizeBundle\Service\FormalizeService;

class SubmissionProcessor extends AbstractDataProcessor
{
    public function __construct(
        private readonly FormalizeService $formalizeService,
        private readonly AuthorizationService $authorizationService)
    {
        parent::__construct();
    }

    protected function addItem(mixed $data, array $filters): Submission
    {
        $submission = $data;
        assert($submission instanceof Submission);

        return $this->formalizeService->addSubmission($submission);
    }

    protected function updateItem(mixed $identifier, mixed $data, mixed $previousData, array $filters): Submission
    {
        $submission = $data;
        assert($submission instanceof Submission);

        return $this->formalizeService->updateSubmission($submission);
    }

    protected function removeItem(mixed $identifier, mixed $data, array $filters): void
    {
        $submission = $data;
        assert($submission instanceof Submission);

        $this->formalizeService->removeSubmission($submission);
    }

    protected function isCurrentUserAuthorizedToAddItem(mixed $item, array $filters): bool
    {
        $submission = $item;
        assert($submission instanceof Submission);

        return $this->authorizationService->isCurrentUserAuthorizedToCreateFormSubmissions($submission->getForm());
    }

    protected function isCurrentUserAuthorizedToAccessItem(int $operation, mixed $item, array $filters): bool
    {
        $submission = $item;
        assert($submission instanceof Submission);

        return match ($operation) {
            self::UPDATE_ITEM_OPERATION => $this->authorizationService->isCurrentUserAuthorizedToUpdateFormSubmissions($submission->getForm()),
            self::REMOVE_ITEM_OPERATION => $this->authorizationService->isCurrentUserAuthorizedToDeleteFormSubmissions($submission->getForm()),
            default => false,
        };
    }
}
