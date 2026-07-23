<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Migrations;

use Dbp\Relay\AuthorizationBundle\API\ResourceActionGrantService;
use Dbp\Relay\FormalizeBundle\Authorization\AuthorizationService;
use Dbp\Relay\FormalizeBundle\Entity\Form;
use Dbp\Relay\FormalizeBundle\Entity\Submission;
use Doctrine\DBAL\Schema\Schema;

class Version20260720144100 extends EntityManagerMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE '.Form::TABLE_NAME.' 
            ADD role_identifier_when_draft BINARY(16) DEFAULT \''.AuthorizationService::EDITOR_WITH_DELETE_ROLE_IDENTIFIER.'\' NOT NULL,
            ADD role_identifier_when_submitted BINARY(16) DEFAULT \''.AuthorizationService::READER_ROLE_IDENTIFIER.'\' NULL');

        $resourceActionGrantService = $this->container->get(ResourceActionGrantService::class);
        assert($resourceActionGrantService instanceof ResourceActionGrantService);

        // loop over all forms:
        foreach ($this->getEntityManager()->getRepository(Form::class)->findAll() as $form) {
            $roleWhenSubmittedIdentifier = match ($form->getAllowedActionsWhenSubmittedRaw()) {
                1 => AuthorizationService::READER_ROLE_IDENTIFIER,
                3 => AuthorizationService::EDITOR_WITHOUT_DELETE_ROLE_IDENTIFIER,
                7 => AuthorizationService::EDITOR_WITH_DELETE_ROLE_IDENTIFIER,
                default => null,
            };
            foreach ($this->getEntityManager()->getRepository(Submission::class)->findBy([
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

                    if (null !== $roleWhenSubmittedIdentifier) {
                        $resourceActionGrantService->addResourceActionGrant(
                            resourceClass: AuthorizationService::SUBMISSION_RESOURCE_CLASS,
                            resourceIdentifier: $submission->getIdentifier(),
                            roleIdentifier: $roleWhenSubmittedIdentifier,
                        );
                    }
                }
            }
        }
    }
}
