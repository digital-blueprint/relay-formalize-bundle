<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Rest;

use Dbp\Relay\CoreBundle\Rest\AbstractDataProcessor;
use Dbp\Relay\FormalizeBundle\Authorization\AuthorizationService;
use Dbp\Relay\FormalizeBundle\Entity\Submission;
use Dbp\Relay\FormalizeBundle\Service\FormalizeService;

class SubmissionProcessor extends AbstractDataProcessor
{
    /**
     * @var FormalizeService
     */
    private $formalizeService;

    /** @var AuthorizationService */
    private $authorizationService;

    public function __construct(FormalizeService $formalizeService, AuthorizationService $authorizationService)
    {
        parent::__construct();

        $this->formalizeService = $formalizeService;
        $this->authorizationService = $authorizationService;
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
        return $this->isAuthenticated();
    }

    protected function isCurrentUserAuthorizedToAddItem($item, array $filters): bool
    {
        $submission = $item;
        assert($submission instanceof Submission);

        return $this->authorizationService->isCurrentUserAuthorizedToCreateFormSubmissions($submission->getForm());
    }

    protected function isCurrentUserAuthorizedToAccessItem(int $operation, $item, array $filters): bool
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
