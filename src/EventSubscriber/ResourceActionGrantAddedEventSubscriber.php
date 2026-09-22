<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\EventSubscriber;

use Dbp\Relay\AuthorizationBundle\Event\ResourceActionGrantAddedEvent;
use Dbp\Relay\FormalizeBundle\Authorization\AuthorizationService;
use Dbp\Relay\FormalizeBundle\Event\FormGrantAddedEvent;
use Dbp\Relay\FormalizeBundle\Event\SubmissionGrantAddedEvent;
use Dbp\Relay\FormalizeBundle\Service\FormalizeService;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ResourceActionGrantAddedEventSubscriber implements EventSubscriberInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public static function getSubscribedEvents(): array
    {
        return [
            ResourceActionGrantAddedEvent::class => 'onResourceActionGrantAddedEvent',
        ];
    }

    public function __construct(
        private readonly FormalizeService $formalizeService,
        private readonly EventDispatcherInterface $eventDispatcher)
    {
        $this->logger = new NullLogger();
    }

    public function onResourceActionGrantAddedEvent(ResourceActionGrantAddedEvent $resourceActionGrantAddedEvent): void
    {
        if ($this->formalizeService->isGrantAddedEventSuspended()) {
            return;
        }

        $followUpEvent = null;
        $resourceActionGrant = $resourceActionGrantAddedEvent->getResourceActionGrant();
        $resourceIdentifier = $resourceActionGrant->getResourceIdentifier();

        switch ($resourceActionGrant->getResourceClass()) {
            case AuthorizationService::SUBMISSION_RESOURCE_CLASS:
                try {
                    $submission = $this->formalizeService->getSubmissionByIdentifier($resourceIdentifier);
                    $followUpEvent = new SubmissionGrantAddedEvent(
                        $submission,
                        $resourceActionGrant
                    );
                } catch (\Throwable $exception) {
                    $this->logger->error('Failed to retrieve submission which an grant was added for', [
                        'exception' => $exception->getMessage(),
                        'identifier' => $resourceIdentifier,
                    ]);
                }
                break;

            case AuthorizationService::FORM_RESOURCE_CLASS:
                try {
                    $form = $this->formalizeService->getFormByIdentifier($resourceIdentifier);
                    $followUpEvent = new FormGrantAddedEvent(
                        $form,
                        $resourceActionGrant
                    );
                } catch (\Throwable $exception) {
                    $this->logger->error('Failed to retrieve form which an grant was added for', [
                        'exception' => $exception->getMessage(),
                        'identifier' => $resourceIdentifier,
                    ]);
                }
                break;
        }

        if ($followUpEvent) {
            $this->eventDispatcher->dispatch($followUpEvent);
        }
    }
}
