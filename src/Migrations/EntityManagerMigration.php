<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Migrations;

use Dbp\Relay\CoreBundle\Doctrine\AbstractEntityManagerMigration;
use Dbp\Relay\FormalizeBundle\DependencyInjection\DbpRelayFormalizeExtension;

abstract class EntityManagerMigration extends AbstractEntityManagerMigration
{
    protected function getEntityManagerId(): string
    {
        return DbpRelayFormalizeExtension::FORMALIZE_ENTITY_MANAGER_ID;
    }
}
