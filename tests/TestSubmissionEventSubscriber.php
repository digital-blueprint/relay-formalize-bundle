<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Tests;

use Dbp\Relay\FormalizeBundle\Event\CreateSubmissionPostEvent;
use Dbp\Relay\FormalizeBundle\Event\SubmissionGrantAddedEvent;
use Dbp\Relay\FormalizeBundle\Event\SubmissionSubmittedPostEvent;
use Dbp\Relay\FormalizeBundle\Event\SubmittedSubmissionUpdatedPostEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class TestSubmissionEventSubscriber implements EventSubscriberInterface
{
    private bool $wasOnCreateSubmissionPostEventCalled = false;
    private bool $wasOnUpdateSubmissionPostEventCalled = false;
    private bool $wasOnSubmissionSubmittedPostEventCalled = false;
    private ?SubmissionGrantAddedEvent $submissionGrantAddedEvent = null;

    public static function getSubscribedEvents(): array
    {
        return [
            CreateSubmissionPostEvent::class => 'onCreateSubmissionPostEvent',
            SubmissionSubmittedPostEvent::class => 'onSubmissionSubmittedPostEvent',
            SubmittedSubmissionUpdatedPostEvent::class => 'onUpdateSubmissionPostEvent',
            SubmissionGrantAddedEvent::class => 'onSubmissionGrantAddedEvent',
        ];
    }

    /**
     * @deprecated
     */
    public function onCreateSubmissionPostEvent(CreateSubmissionPostEvent $event): void
    {
        $this->wasOnCreateSubmissionPostEventCalled = true;
    }

    public function onSubmissionSubmittedPostEvent(SubmissionSubmittedPostEvent $event): void
    {
        $this->wasOnSubmissionSubmittedPostEventCalled = true;
    }

    public function onUpdateSubmissionPostEvent(SubmittedSubmissionUpdatedPostEvent $event): void
    {
        $this->wasOnUpdateSubmissionPostEventCalled = true;
    }

    public function onSubmissionGrantAddedEvent(SubmissionGrantAddedEvent $event): void
    {
        $this->submissionGrantAddedEvent = $event;
    }

    /**
     * @deprecated
     */
    public function wasCreateSubmissionPostEventCalled(): bool
    {
        return $this->wasOnCreateSubmissionPostEventCalled;
    }

    public function wasSubmissionSubmittedPostEventCalled(): bool
    {
        return $this->wasOnSubmissionSubmittedPostEventCalled;
    }

    public function wasUpdateSubmissionPostEventCalled(): bool
    {
        return $this->wasOnUpdateSubmissionPostEventCalled;
    }

    public function getSubmissionGrantAddedEvent(): ?SubmissionGrantAddedEvent
    {
        return $this->submissionGrantAddedEvent;
    }

    public function reset(): void
    {
        $this->wasOnCreateSubmissionPostEventCalled = false;
        $this->wasOnSubmissionSubmittedPostEventCalled = false;
        $this->wasOnUpdateSubmissionPostEventCalled = false;
        $this->submissionGrantAddedEvent = null;
    }
}
