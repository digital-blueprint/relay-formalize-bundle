<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Migrations;

use Dbp\Relay\AuthorizationBundle\API\ResourceActionGrantService;
use Dbp\Relay\FormalizeBundle\Authorization\AuthorizationService;
use Doctrine\DBAL\Schema\Schema;

class Version20261020102100 extends EntityManagerMigration
{
    public function getDescription(): string
    {
        return 'add form roles and update role names (gender German names)';
    }

    public function up(Schema $schema): void
    {
        $resourceActionGrantService = $this->container->get(ResourceActionGrantService::class);
        assert($resourceActionGrantService instanceof ResourceActionGrantService);

        AuthorizationService::addOrUpdateRoles($resourceActionGrantService);
    }
}
