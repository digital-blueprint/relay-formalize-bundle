<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Rest;

use Dbp\Relay\CoreBundle\Rest\AbstractDataProcessor;
use Dbp\Relay\FormalizeBundle\Authorization\AuthorizationService;
use Dbp\Relay\FormalizeBundle\Entity\Form;
use Dbp\Relay\FormalizeBundle\Service\FormalizeService;

class FormProcessor extends AbstractDataProcessor
{
    private FormalizeService $formalizeService;
    private AuthorizationService $authorizationService;

    public function __construct(FormalizeService $formalizeService, AuthorizationService $authorizationService)
    {
        parent::__construct();

        $this->formalizeService = $formalizeService;
        $this->authorizationService = $authorizationService;
    }

    protected function addItem($data, array $filters): Form
    {
        $form = $data;
        assert($form instanceof Form);

        return $this->formalizeService->addForm($form);
    }

    protected function removeItem($identifier, $data, array $filters): void
    {
        $form = $data;
        assert($form instanceof Form);

        $this->formalizeService->removeForm($form);
    }

    protected function updateItem($identifier, $data, $previousData, array $filters)
    {
        $form = $data;
        assert($form instanceof Form);

        return $this->formalizeService->updateForm($form);
    }

    protected function isUserGrantedOperationAccess(int $operation): bool
    {
        return $this->isAuthenticated();
    }

    protected function isCurrentUserAuthorizedToAccessItem(int $operation, $item, array $filters): bool
    {
        $form = $item;
        assert($form instanceof Form);

        switch ($operation) {
            case self::UPDATE_ITEM_OPERATION:
                return $this->authorizationService->isCurrentUserAuthorizedToUpdateForm($form);
            case self::REMOVE_ITEM_OPERATION:
                return $this->authorizationService->isCurrentUserAuthorizedToDeleteForm($form);
        }

        return false;
    }

    protected function isCurrentUserAuthorizedToAddItem($item, array $filters): bool
    {
        return $this->authorizationService->isCurrentUserAuthorizedToCreateForms();
    }
}
