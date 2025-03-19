<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Rest;

use Dbp\Relay\CoreBundle\Rest\AbstractDataProvider;
use Dbp\Relay\FormalizeBundle\Authorization\AuthorizationService;
use Dbp\Relay\FormalizeBundle\Entity\SubmittedFile;
use Dbp\Relay\FormalizeBundle\Service\SubmittedFileService;

/**
 * @extends AbstractDataProvider<SubmittedFile>
 */
class SubmittedFileProvider extends AbstractDataProvider
{
    public function __construct(
        private readonly SubmittedFileService $submittedFileService,
        private readonly AuthorizationService $authorizationService)
    {
        parent::__construct();
    }

    protected function getItemById(string $id, array $filters = [], array $options = []): SubmittedFile
    {
        return $this->submittedFileService->getSubmittedFileByIdentifier($id);
    }

    protected function getPage(int $currentPageNumber, int $maxNumItemsPerPage, array $filters = [], array $options = []): array
    {
        throw new \RuntimeException('collection endpoint not available');
    }

    protected function isCurrentUserAuthorizedToAccessItem(int $operation, mixed $item, array $filters): bool
    {
        $submittedFile = $item;
        assert($submittedFile instanceof SubmittedFile);

        return $this->authorizationService->isCurrentUserAuthorizedToReadSubmission($submittedFile->getSubmission());
    }
}
