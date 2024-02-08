<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Rest;

use Dbp\Relay\CoreBundle\Rest\AbstractDataProvider;
use Dbp\Relay\FormalizeBundle\Authorization\AuthorizationService;
use Dbp\Relay\FormalizeBundle\Entity\Form;
use Dbp\Relay\FormalizeBundle\Service\FormalizeService;

class FormProvider extends AbstractDataProvider
{
    /** @var FormalizeService */
    private $formalizeService;

    /** @var AuthorizationService */
    private $authorizationService;

    public function __construct(FormalizeService $formalizeService, AuthorizationService $authorizationService)
    {
        parent::__construct();

        $this->formalizeService = $formalizeService;
        $this->authorizationService = $authorizationService;
    }

    protected function getItemById(string $id, array $filters = [], array $options = []): ?object
    {
        return $this->formalizeService->getForm($id);
    }

    protected function getPage(int $currentPageNumber, int $maxNumItemsPerPage, array $filters = [], array $options = []): array
    {
        return $this->authorizationService->getFormsUserCanRead(
            $this->formalizeService->getForms($currentPageNumber, $maxNumItemsPerPage));
    }

    protected function isUserGrantedOperationAccess(int $operation): bool
    {
        return $this->isAuthenticated();
        //            && $this->getCurrentUserAttribute('ROLE_DEVELOPER');
    }

    protected function isCurrentUserAuthorizedToAccessItem(int $operation, $item, array $filters): bool
    {
        $form = $item;
        assert($form instanceof Form);

        return $this->authorizationService->canCurrentUserReadForm($form);
    }
}
