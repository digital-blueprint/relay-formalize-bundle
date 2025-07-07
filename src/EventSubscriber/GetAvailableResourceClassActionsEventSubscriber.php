<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\EventSubscriber;

use Dbp\Relay\AuthorizationBundle\Event\GetAvailableResourceClassActionsEvent;
use Dbp\Relay\FormalizeBundle\Authorization\AuthorizationService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class GetAvailableResourceClassActionsEventSubscriber implements EventSubscriberInterface
{
    private const AVAILABLE_FORM_ITEM_ACTIONS = AuthorizationService::FORM_ITEM_ACTIONS;
    private const AVAILABLE_FORM_COLLECTION_ACTIONS = [AuthorizationService::CREATE_FORMS_ACTION];

    private const AVAILABLE_SUBMISSION_ITEM_ACTIONS = AuthorizationService::SUBMISSION_ITEM_ACTIONS;
    private const AVAILABLE_SUBMISSION_COLLECTION_ACTIONS = [];

    public static function getSubscribedEvents(): array
    {
        return [
            GetAvailableResourceClassActionsEvent::class => 'onGetAvailableResourceClassActionsEvent',
        ];
    }

    public function onGetAvailableResourceClassActionsEvent(GetAvailableResourceClassActionsEvent $event): void
    {
        switch ($event->getResourceClass()) {
            case AuthorizationService::FORM_RESOURCE_CLASS:
                $event->setItemActions(self::AVAILABLE_FORM_ITEM_ACTIONS);
                $event->setCollectionActions(self::AVAILABLE_FORM_COLLECTION_ACTIONS);
                break;
            case AuthorizationService::SUBMISSION_RESOURCE_CLASS:
                $event->setItemActions(self::AVAILABLE_SUBMISSION_ITEM_ACTIONS);
                $event->setCollectionActions(self::AVAILABLE_SUBMISSION_COLLECTION_ACTIONS);
                break;
            default:
                break;
        }
    }
}
