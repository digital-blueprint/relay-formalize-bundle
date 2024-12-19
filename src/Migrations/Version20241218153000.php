<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Migrations;

use Dbp\Relay\FormalizeBundle\Authorization\AuthorizationService;
use Dbp\Relay\FormalizeBundle\Common\DemoForm;
use Doctrine\DBAL\Schema\Schema;

final class Version20241218153000 extends EntityManagerMigration
{
    public function getDescription(): string
    {
        return 'Create demo form registration form grants and enable submission level authorization.';
    }

    public function up(Schema $schema): void
    {
        $FORM_RESOURCE_CLASS = AuthorizationService::FORM_RESOURCE_CLASS;
        $CREATE_SUBMISSIONS_FORM_ACTION = AuthorizationService::CREATE_SUBMISSIONS_FORM_ACTION;
        $READ_FORM_ACTION = AuthorizationService::READ_FORM_ACTION;
        $DEMO_FORM_REGISTRATIONS_FORM_IDENTIFIER = DemoForm::REGISTRATIONS_FORM_IDENTIFIER;
        $demoFormRegistrationsAuthorizationResourceIdentifier = self::createBinaryUuidForInsertStatement();
        $groupIdentifier = 'everybody';

        // Create authorization resource for demo form
        $this->addSql("INSERT INTO authorization_resources (identifier, resource_class, resource_identifier)
           VALUES ($demoFormRegistrationsAuthorizationResourceIdentifier, '$FORM_RESOURCE_CLASS', '$DEMO_FORM_REGISTRATIONS_FORM_IDENTIFIER')");

        // Add grants for the demo form
        // - Members of dynamic group 'everybody' may read the form
        // - Members of dynamic group 'everybody' may post submissions
        $this->addSql('INSERT INTO authorization_resource_action_grants (identifier, authorization_resource_identifier, dynamic_group_identifier, action)
           VALUES ('.self::createBinaryUuidForInsertStatement().", $demoFormRegistrationsAuthorizationResourceIdentifier, '$groupIdentifier', '$READ_FORM_ACTION')");
        $this->addSql('INSERT INTO authorization_resource_action_grants (identifier, authorization_resource_identifier, dynamic_group_identifier, action)
           VALUES ('.self::createBinaryUuidForInsertStatement().", $demoFormRegistrationsAuthorizationResourceIdentifier, '$groupIdentifier', '$CREATE_SUBMISSIONS_FORM_ACTION')");

        // Allow all users to manage their own submissions
        $this->addSql('UPDATE formalize_forms SET submission_level_authorization = "1" WHERE identifier = "$DEMO_FORM_REGISTRATIONS_FORM_IDENTIFIER"');
    }

    public function down(Schema $schema): void
    {
    }
}
