<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Migrations;

use Dbp\Relay\AuthorizationBundle\API\ResourceActionGrantService;
use Dbp\Relay\FormalizeBundle\Authorization\AuthorizationService;
use Doctrine\DBAL\Schema\Schema;

class Version20261023130100 extends EntityManagerMigration
{
    public function getDescription(): string
    {
        return 'Alter table formalize_localized_form_names to cascade delete on form deletion';
    }

    public function up(Schema $schema): void
    {
        $resourceActionGrantService = $this->container->get(ResourceActionGrantService::class);
        assert($resourceActionGrantService instanceof ResourceActionGrantService);

        AuthorizationService::addOrUpdateAvailableResourceClassActions($resourceActionGrantService);

        // remove deprecate 'DbpRelayFormalizeSubmissionCollection' resource class:
        $resourceActionGrantService->addOrUpdateAvailableResourceClassActions(
            'DbpRelayFormalizeSubmissionCollection', [], []
        );
    }
}
