<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Rest;

use Dbp\Relay\CoreBundle\Rest\AbstractDataProcessor;
use Dbp\Relay\FormalizeBundle\Authorization\AuthorizationService;
use Dbp\Relay\FormalizeBundle\Entity\Form;
use Dbp\Relay\FormalizeBundle\Service\FormalizeService;

class FormProcessor extends AbstractDataProcessor
{
    public function __construct(
        protected readonly FormalizeService $formalizeService,
        protected readonly AuthorizationService $authorizationService)
    {
        parent::__construct();
    }

    protected function addItem(mixed $data, array $filters): Form
    {
        $form = $data;
        assert($form instanceof Form);

        $form = $this->formalizeService->addForm($form);
        FormalizeService::setDataFeedSchemaForBackwardCompatibility([$form]);

        return $form;
    }

    protected function removeItem(mixed $identifier, mixed $data, array $filters): void
    {
        $form = $data;
        assert($form instanceof Form);

        $this->formalizeService->removeForm($form);
    }

    protected function updateItem(mixed $identifier, mixed $data, mixed $previousData, array $filters): Form
    {
        $form = $data;
        assert($form instanceof Form);

        $form = $this->formalizeService->updateForm($form);
        FormalizeService::setDataFeedSchemaForBackwardCompatibility([$form]);

        return $form;
    }

    protected function isCurrentUserAuthorizedToAccessItem(int $operation, mixed $item, array $filters): bool
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

    protected function isCurrentUserAuthorizedToAddItem(mixed $item, array $filters): bool
    {
        return $this->authorizationService->isCurrentUserAuthorizedToCreateForms();
    }
}
