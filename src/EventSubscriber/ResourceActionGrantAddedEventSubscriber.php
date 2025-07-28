<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\EventSubscriber;

use Dbp\Relay\AuthorizationBundle\Event\ResourceActionGrantAddedEvent;
use Dbp\Relay\FormalizeBundle\Authorization\AuthorizationService;
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
        if ($resourceActionGrantAddedEvent->getResourceClass() === AuthorizationService::SUBMISSION_RESOURCE_CLASS
            && ($submissionIdentifier = $resourceActionGrantAddedEvent->getResourceIdentifier())) {
            try {
                $submission = $this->formalizeService->getSubmissionByIdentifier($submissionIdentifier);
            } catch (\Exception $exception) {
                $this->logger->error('Failed to retrieve submission which an grant was added for', [
                    'exception' => $exception->getMessage(),
                    'submissionIdentifier' => $submissionIdentifier,
                ]);

                return;
            }
            $event = new SubmissionGrantAddedEvent($submission,
                $resourceActionGrantAddedEvent->getAction(),
                $resourceActionGrantAddedEvent->getUserIdentifier(),
                $resourceActionGrantAddedEvent->getGroupIdentifier(),
                $resourceActionGrantAddedEvent->getDynamicGroupIdentifier()
            );

            $this->eventDispatcher->dispatch($event);
        }
    }
}
