<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Tests;

use Dbp\Relay\FormalizeBundle\Event\CreateSubmissionPostEvent;
use Dbp\Relay\FormalizeBundle\Event\UpdateSubmissionPostEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class TestSubmissionEventSubscriber implements EventSubscriberInterface
{
    private bool $wasOnCreateSubmissionPostEventCalled = false;
    private bool $wasOnUpdateSubmissionPostEventCalled = false;

    public static function getSubscribedEvents(): array
    {
        return [
            CreateSubmissionPostEvent::class => 'onCreateSubmissionPostEvent',
            UpdateSubmissionPostEvent::class => 'onUpdateSubmissionPostEvent',
        ];
    }

    public function onCreateSubmissionPostEvent(CreateSubmissionPostEvent $event): void
    {
        $this->wasOnCreateSubmissionPostEventCalled = true;
    }

    public function onUpdateSubmissionPostEvent(UpdateSubmissionPostEvent $event): void
    {
        $this->wasOnUpdateSubmissionPostEventCalled = true;
    }

    public function wasCreateSubmissionPostEventCalled(): bool
    {
        return $this->wasOnCreateSubmissionPostEventCalled;
    }

    public function wasUpdateSubmissionPostEventCalled(): bool
    {
        return $this->wasOnUpdateSubmissionPostEventCalled;
    }
}
