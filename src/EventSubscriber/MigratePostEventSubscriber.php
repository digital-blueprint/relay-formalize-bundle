<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\EventSubscriber;

use Dbp\Relay\AuthorizationBundle\API\ResourceActionGrantService;
use Dbp\Relay\CoreBundle\DB\MigratePostEvent;
use Dbp\Relay\FormalizeBundle\Authorization\AuthorizationService;
use Dbp\Relay\FormalizeBundle\Service\FormalizeService;
use Dbp\Relay\FormalizeBundle\Service\SubmittedFileService;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

readonly class MigratePostEventSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            MigratePostEvent::class => 'onMigratePostEvent',
        ];
    }

    public function __construct(
        private ResourceActionGrantService $resourceActionGrantService,
        private FormalizeService $formalizeService,
        private SubmittedFileService $submittedFileService)
    {
    }

    /**
     * @throws \Throwable
     */
    public function onMigratePostEvent(MigratePostEvent $event): void
    {
        AuthorizationService::setAvailableResourceClassActions($this->resourceActionGrantService);

        $this->migrateFromFormActionToSubmissionCollectionAction($event->getOutput());
        $this->submittedFileService->migrateToCurrentFileDataVersion($event->getOutput());
    }

    private function migrateFromFormActionToSubmissionCollectionAction(OutputInterface $output): void
    {
        $actionsToMigrateMap = [
            AuthorizationService::MANAGE_ACTION => AuthorizationService::MANAGE_ACTION,
            'read_submissions' => AuthorizationService::READ_SUBMISSIONS_ACTION,
            'update_submissions' => AuthorizationService::UPDATE_SUBMISSIONS_ACTION,
            'delete_submissions' => AuthorizationService::DELETE_SUBMISSIONS_ACTION,
            AuthorizationService::CREATE_SUBMISSIONS_ACTION => AuthorizationService::CREATE_SUBMISSIONS_ACTION,
        ];

        foreach ($this->formalizeService->getForms(0, 99999) as $form) {
            // test if migration is needed:
            if ([] === $this->resourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
                AuthorizationService::SUBMISSION_COLLECTION_RESOURCE_CLASS, $form->getIdentifier())) {
                foreach ($this->resourceActionGrantService->getResourceActionGrantsForResourceClassAndIdentifier(
                    AuthorizationService::FORM_RESOURCE_CLASS, $form->getIdentifier(),
                    ignoreActionAvailability: true) as $resourceActionGrant) {
                    if ($targetAction = $actionsToMigrateMap[$resourceActionGrant->getAction()] ?? null) {
                        $output->writeln('Migrating resource action grant '.$resourceActionGrant->getIdentifier()
                            .' of form '.$form->getIdentifier().' ('.$form->getName().') from form action '
                            .$resourceActionGrant->getAction().' to submission collection action '.$targetAction);
                        $this->resourceActionGrantService->addResourceActionGrant(
                            AuthorizationService::SUBMISSION_COLLECTION_RESOURCE_CLASS,
                            $form->getIdentifier(),
                            $targetAction,
                            $resourceActionGrant->getUserIdentifier(),
                            $resourceActionGrant->getGroup()?->getIdentifier(),
                            $resourceActionGrant->getDynamicGroupIdentifier()
                        );
                        // manage is copied, all other actions are moved:
                        if ($resourceActionGrant->getAction() !== AuthorizationService::MANAGE_ACTION) {
                            $this->resourceActionGrantService->removeResourceActionGrant($resourceActionGrant->getIdentifier());
                        }
                    }
                }
            }
        }
    }
}
