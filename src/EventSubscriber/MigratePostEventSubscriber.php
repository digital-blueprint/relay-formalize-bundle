<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\EventSubscriber;

use Dbp\Relay\AuthorizationBundle\API\ResourceActionGrantService;
use Dbp\Relay\CoreBundle\DB\MigratePostEvent;
use Dbp\Relay\FormalizeBundle\Authorization\AuthorizationService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

readonly class MigratePostEventSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            MigratePostEvent::class => 'onMigratePostEvent',
        ];
    }

    public function __construct(private ResourceActionGrantService $resourceActionGrantService)
    {
    }

    public function onMigratePostEvent(MigratePostEvent $event): void
    {
        AuthorizationService::setAvailableResourceClassActions($this->resourceActionGrantService);
    }
}
