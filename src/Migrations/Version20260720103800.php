<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Migrations;

use Dbp\Relay\AuthorizationBundle\API\ResourceActionGrantService;
use Dbp\Relay\FormalizeBundle\Authorization\AuthorizationService;
use Dbp\Relay\FormalizeBundle\EventSubscriber\MigratePostEventSubscriber;
use Doctrine\DBAL\Schema\Schema;

class Version20260720103800 extends EntityManagerMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $resourceActionGrantService = $this->container->get(ResourceActionGrantService::class);
        assert($resourceActionGrantService instanceof ResourceActionGrantService);
        $entityManager = $resourceActionGrantService->getEntityManager();

        // in the table authorization_available_resource_class_actions,
        // change the resource_class of the 'create_submissions' action
        // from 'DbpRelayFormalizeSubmissionCollection' to 'DbpRelayFormalizeForm'
        $entityManager->getConnection()->executeQuery('
            UPDATE authorization_available_resource_class_actions SET resource_class = :resource_class_new
                WHERE resource_class = :resource_class_old AND action = :action', [
            'resource_class_new' => AuthorizationService::FORM_RESOURCE_CLASS,
            'resource_class_old' => MigratePostEventSubscriber::DEPRECATE_SUBMISSION_COLLECTION_RESOURCE_CLASS,
            'action' => AuthorizationService::CREATE_SUBMISSIONS_FORM_ACTION,
        ]);

        // in the table authorization_available_resource_class_actions, change the authz resource of 'create_submissions' grants
        // from 'DbpRelayFormalizeSubmissionCollection' to 'DbpRelayFormalizeForm' (with same resource identifier)
        $entityManager->getConnection()->executeQuery('
            UPDATE authorization_resource_action_grants rag
            JOIN authorization_available_resource_class_actions arca
                ON arca.identifier = rag.available_resource_class_action_identifier
            JOIN authorization_resources ar
                ON rag.authorization_resource_identifier = ar.identifier
            JOIN authorization_resources ar_new
                ON ar.resource_identifier = ar_new.resource_identifier
                AND ar_new.resource_class = :resource_class_new
            SET rag.authorization_resource_identifier = ar_new.identifier
            WHERE arca.action = :action', [
            'resource_class_new' => AuthorizationService::FORM_RESOURCE_CLASS,
            'action' => AuthorizationService::CREATE_SUBMISSIONS_FORM_ACTION,
        ]);

        // make 'DbpRelayFormalizeSubmissionCollection' authz resources 'DbpRelayFormalizeSubmission' authz resource groups
        $entityManager->getConnection()->executeQuery(
            'UPDATE authorization_resources SET resource_class = :resource_class_new, resource_type = :resource_type_new
                  WHERE resource_class = :resource_class_old', [
                'resource_class_new' => AuthorizationService::SUBMISSION_RESOURCE_CLASS,
                'resource_type_new' => ResourceActionGrantService::RESOURCE_GROUP_RESOURCE_TYPE,
                'resource_class_old' => MigratePostEventSubscriber::DEPRECATE_SUBMISSION_COLLECTION_RESOURCE_CLASS,
            ]);

        // replace the actions of the deprecated submission collection resource class
        // by the actions of the submission resource class (read, update, delete)
        $entityManager->getConnection()->executeQuery('
            UPDATE authorization_resource_action_grants rag
            JOIN authorization_available_resource_class_actions arca_old
                ON arca_old.identifier = rag.available_resource_class_action_identifier
                AND arca_old.resource_class = :resource_class_old
            JOIN authorization_available_resource_class_actions arca_new
                ON arca_old.action = arca_new.action AND arca_new.resource_class = :resource_class_new
            SET rag.available_resource_class_action_identifier = arca_new.identifier', [
            'resource_class_new' => AuthorizationService::SUBMISSION_RESOURCE_CLASS,
            'resource_class_old' => MigratePostEventSubscriber::DEPRECATE_SUBMISSION_COLLECTION_RESOURCE_CLASS,
        ]);

        // delete from available_resource_class_actions the deprecated submission collection resource class actions
        $entityManager->getConnection()->executeQuery('
            DELETE FROM authorization_available_resource_class_actions WHERE resource_class = :resource_class_old', [
            'resource_class_old' => MigratePostEventSubscriber::DEPRECATE_SUBMISSION_COLLECTION_RESOURCE_CLASS,
        ]);

        AuthorizationService::ensureRoles($resourceActionGrantService);
        AuthorizationService::ensureAvailableResourceClassActions($resourceActionGrantService);
    }
}
