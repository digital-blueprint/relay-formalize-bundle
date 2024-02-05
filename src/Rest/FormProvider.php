<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Rest;

use Dbp\Relay\CoreBundle\Rest\AbstractDataProvider;
use Dbp\Relay\FormalizeBundle\Service\FormalizeService;

class FormProvider extends AbstractDataProvider
{
    /** @var FormalizeService */
    private $formalizeService;

    public function __construct(FormalizeService $formalizeService)
    {
        parent::__construct();

        $this->formalizeService = $formalizeService;
    }

    protected function getItemById(string $id, array $filters = [], array $options = []): ?object
    {
        return $this->formalizeService->getForm($id);
    }

    protected function getPage(int $currentPageNumber, int $maxNumItemsPerPage, array $filters = [], array $options = []): array
    {
        return $this->formalizeService->getForms($currentPageNumber, $maxNumItemsPerPage);
    }

    protected function isUserGrantedOperationAccess(int $operation): bool
    {
        return $this->isAuthenticated()
            && $this->getCurrentUserAttribute('ROLE_DEVELOPER');
    }
}
