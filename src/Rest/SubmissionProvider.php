<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Rest;

use Dbp\Relay\CoreBundle\Rest\AbstractDataProvider;
use Dbp\Relay\FormalizeBundle\Service\FormalizeService;

class SubmissionProvider extends AbstractDataProvider
{
    /**
     * @var FormalizeService
     */
    private $formalizeService;

    public function __construct(FormalizeService $formalizeService)
    {
        parent::__construct();

        $this->formalizeService = $formalizeService;
    }

    protected function getItemById(string $id, array $filters = [], array $options = []): ?object
    {
        return $this->formalizeService->getSubmissionByIdentifier($id);
    }

    protected function getPage(int $currentPageNumber, int $maxNumItemsPerPage, array $filters = [], array $options = []): array
    {
        return $this->formalizeService->getSubmissions($currentPageNumber, $maxNumItemsPerPage, $filters);
    }

    protected function isUserGrantedOperationAccess(int $operation): bool
    {
        return $this->isAuthenticated()
            && ($this->getCurrentUserAttribute('SCOPE_FORMALIZE')
                || $this->getCurrentUserAttribute('ROLE_FORMALIZE_TEST_USER')
                || $this->getCurrentUserAttribute('ROLE_DEVELOPER'));
    }
}
