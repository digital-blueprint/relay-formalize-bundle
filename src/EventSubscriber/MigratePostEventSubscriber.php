<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\EventSubscriber;

use Dbp\Relay\CoreBundle\DB\MigratePostEvent;
use Dbp\Relay\FormalizeBundle\Service\SubmittedFileService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

readonly class MigratePostEventSubscriber implements EventSubscriberInterface
{
    public const DEPRECATE_SUBMISSION_COLLECTION_RESOURCE_CLASS = 'DbpRelayFormalizeSubmissionCollection';

    public static function getSubscribedEvents(): array
    {
        return [
            MigratePostEvent::class => 'onMigratePostEvent',
        ];
    }

    public function __construct(
        private SubmittedFileService $submittedFileService)
    {
    }

    /**
     * @throws \Throwable
     */
    public function onMigratePostEvent(MigratePostEvent $event): void
    {
        try {
            $this->submittedFileService->migrateToCurrentFileDataVersion($event->getOutput());
        } catch (\Throwable $throwable) {
            // TODO: try to do ignore this only for tests, once we know if we are currently running them
            $event->getOutput()->writeln('Error migrating submitted files to current file data version: '.$throwable->getMessage());
        }
    }
}
