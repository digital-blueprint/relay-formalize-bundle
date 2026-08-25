<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Migrations;

use Dbp\Relay\AuthorizationBundle\API\ResourceActionGrantService;
use Dbp\Relay\FormalizeBundle\Authorization\AuthorizationService;
use Dbp\Relay\FormalizeBundle\Entity\Form;
use Dbp\Relay\FormalizeBundle\Entity\Submission;
use Doctrine\DBAL\Schema\Schema;

class Version20260727191100 extends EntityManagerMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $resourceActionGrantService = $this->container->get(ResourceActionGrantService::class);
        assert($resourceActionGrantService instanceof ResourceActionGrantService);

        $entityManager = $this->getEntityManager();

        // loop over all forms:
        foreach ($entityManager->getRepository(Form::class)->findAll() as $form) {
            $roleWhenSubmittedIdentifier = match (
                $form->getAllowedActionsWhenSubmittedRaw() & (Form::READ_SUBMISSION_ACTION_FLAG | Form::UPDATE_SUBMISSION_ACTION_FLAG | Form::DELETE_SUBMISSION_ACTION_FLAG)) {
                Form::READ_SUBMISSION_ACTION_FLAG => AuthorizationService::READER_ROLE_IDENTIFIER,
                (Form::READ_SUBMISSION_ACTION_FLAG | Form::UPDATE_SUBMISSION_ACTION_FLAG) => AuthorizationService::EDITOR_WITHOUT_DELETE_ROLE_IDENTIFIER,
                (Form::READ_SUBMISSION_ACTION_FLAG | Form::UPDATE_SUBMISSION_ACTION_FLAG | Form::DELETE_SUBMISSION_ACTION_FLAG) => AuthorizationService::EDITOR_WITH_DELETE_ROLE_IDENTIFIER,
                default => null,
            };
            $form->setRoleIdentifierWhenSubmitted($roleWhenSubmittedIdentifier);
            $entityManager->persist($form);

            foreach ($entityManager->getRepository(Submission::class)->findBy([
                'form' => $form->getIdentifier()]) as $submission) {
                if ($submission->isSubmitted()) {
                    if ($form->getGrantBasedSubmissionAuthorization()) {
                        $resourceActionGrantService->removeGrantsForResource(
                            resourceClass: AuthorizationService::SUBMISSION_RESOURCE_CLASS,
                            resourceIdentifier: $submission->getIdentifier(),
                        );
                    } else {
                        // also creates a new authz resource for the submission:
                        $resourceActionGrantService->addResourceToGroupResource(
                            AuthorizationService::SUBMISSION_RESOURCE_CLASS,
                            $form->getIdentifier(),
                            $submission->getIdentifier()
                        );
                    }

                    $creatorId = $submission->getCreatorId();
                    if (null !== $roleWhenSubmittedIdentifier
                        && null !== $creatorId) {
                        $resourceActionGrantService->addResourceActionGrant(
                            resourceClass: AuthorizationService::SUBMISSION_RESOURCE_CLASS,
                            resourceIdentifier: $submission->getIdentifier(),
                            roleIdentifier: $roleWhenSubmittedIdentifier,
                            userIdentifier: $creatorId,
                            shareable: true
                        );
                    }
                }
            }
        }
        $entityManager->flush();
    }
}
