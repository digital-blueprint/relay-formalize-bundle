<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Rest;

use Dbp\Relay\CoreBundle\Rest\AbstractDataProvider;
use Dbp\Relay\CoreBundle\Rest\Query\Pagination\Pagination;
use Dbp\Relay\FormalizeBundle\Authorization\AuthorizationService;
use Dbp\Relay\FormalizeBundle\Entity\Submission;
use Dbp\Relay\FormalizeBundle\Service\FormalizeService;

/**
 * @extends AbstractDataProvider<Submission>
 */
class SubmissionProvider extends AbstractDataProvider
{
    public function __construct(
        private readonly FormalizeService $formalizeService,
        private readonly AuthorizationService $authorizationService)
    {
        parent::__construct();
    }

    protected function getItemById(string $id, array $filters = [], array $options = []): ?object
    {
        return $this->formalizeService->getSubmissionByIdentifier($id);
    }

    protected function getPage(int $currentPageNumber, int $maxNumItemsPerPage, array $filters = [], array $options = []): array
    {
        $firstResultIndex = Pagination::getFirstItemIndex($currentPageNumber, $maxNumItemsPerPage);

        return $this->formalizeService->getFormSubmissionsCurrentUserIsAuthorizedToRead(
            Common::getFormIdentifier($filters), $firstResultIndex, $maxNumItemsPerPage, $filters);
    }

    protected function isCurrentUserAuthorizedToAccessItem(int $operation, mixed $item, array $filters): bool
    {
        $submission = $item;
        assert($submission instanceof Submission);

        return $this->authorizationService->isCurrentUserAuthorizedToReadSubmission($submission);
    }
}
