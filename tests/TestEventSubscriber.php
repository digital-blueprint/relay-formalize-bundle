<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Tests;

use Dbp\Relay\FormalizeBundle\Event\FormGrantAddedEvent;
use Dbp\Relay\FormalizeBundle\Event\SubmissionGrantAddedEvent;
use Dbp\Relay\FormalizeBundle\Event\SubmissionSubmittedPostEvent;
use Dbp\Relay\FormalizeBundle\Event\SubmittedSubmissionUpdatedPostEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class TestEventSubscriber implements EventSubscriberInterface
{
    private bool $wasOnCreateSubmissionPostEventCalled = false;
    private bool $wasOnUpdateSubmissionPostEventCalled = false;
    private bool $wasOnSubmissionSubmittedPostEventCalled = false;
    private ?SubmissionGrantAddedEvent $submissionGrantAddedEvent = null;
    private ?FormGrantAddedEvent $formGrantAddedEvent = null;

    public static function getSubscribedEvents(): array
    {
        return [
            SubmissionSubmittedPostEvent::class => 'onSubmissionSubmittedPostEvent',
            SubmittedSubmissionUpdatedPostEvent::class => 'onUpdateSubmissionPostEvent',
            SubmissionGrantAddedEvent::class => 'onSubmissionGrantAddedEvent',
            FormGrantAddedEvent::class => 'onFormGrantAddedEvent',
        ];
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

    public function onFormGrantAddedEvent(FormGrantAddedEvent $event): void
    {
        $this->formGrantAddedEvent = $event;
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

    public function getFormGrantAddedEvent(): ?FormGrantAddedEvent
    {
        return $this->formGrantAddedEvent;
    }

    public function reset(): void
    {
        $this->wasOnCreateSubmissionPostEventCalled = false;
        $this->wasOnSubmissionSubmittedPostEventCalled = false;
        $this->wasOnUpdateSubmissionPostEventCalled = false;
        $this->submissionGrantAddedEvent = null;
        $this->formGrantAddedEvent = null;
    }
}
