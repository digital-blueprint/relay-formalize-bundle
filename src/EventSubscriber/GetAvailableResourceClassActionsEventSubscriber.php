<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\EventSubscriber;

use Dbp\Relay\AuthorizationBundle\Event\GetAvailableResourceClassActionsEvent;
use Dbp\Relay\FormalizeBundle\Authorization\AuthorizationService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class GetAvailableResourceClassActionsEventSubscriber implements EventSubscriberInterface
{
    private const AVAILABLE_FORM_ITEM_ACTIONS = [
        AuthorizationService::READ_FORM_ACTION => [
            'en' => 'Read',
            'de' => 'Lesen',
        ],
        AuthorizationService::UPDATE_FORM_ACTION => [
            'en' => 'Update',
            'de' => 'Aktualisieren',
        ],
        AuthorizationService::DELETE_FORM_ACTION => [
            'en' => 'Delete',
            'de' => 'Löschen',
        ],
        AuthorizationService::CREATE_SUBMISSIONS_FORM_ACTION => [
            'en' => 'Create Submissions',
            'de' => 'Einreichungen erstellen',
        ],
        AuthorizationService::READ_SUBMISSIONS_FORM_ACTION => [
            'en' => 'Read Submissions',
            'de' => 'Einreichungen lesen',
        ],
        AuthorizationService::UPDATE_SUBMISSIONS_FORM_ACTION => [
            'en' => 'Update Submissions',
            'de' => 'Einreichungen aktualisieren',
        ],
        AuthorizationService::DELETE_SUBMISSIONS_FORM_ACTION => [
            'en' => 'Delete Submissions',
            'de' => 'Einreichungen löschen',
        ],
    ];

    private const AVAILABLE_FORM_COLLECTION_ACTIONS = [
        AuthorizationService::CREATE_FORMS_ACTION => [
            'en' => 'Create',
            'de' => 'Erstellen',
        ],
    ];

    private const AVAILABLE_SUBMISSION_ITEM_ACTIONS = [
        AuthorizationService::READ_SUBMISSION_ACTION => [
            'en' => 'Read',
            'de' => 'Lesen',
        ],
        AuthorizationService::UPDATE_SUBMISSION_ACTION => [
            'en' => 'Update',
            'de' => 'Aktualisieren',
        ],
        AuthorizationService::DELETE_SUBMISSION_ACTION => [
            'en' => 'Delete',
            'de' => 'Löschen',
        ],
    ];
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
