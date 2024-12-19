<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Migrations;

use Dbp\Relay\FormalizeBundle\DependencyInjection\DbpRelayFormalizeExtension;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\ORM\EntityManager;
use Ramsey\Uuid\Uuid;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class EntityManagerMigration extends AbstractMigration implements ContainerAwareInterface
{
    protected ?ContainerInterface $container = null;

    public function setContainer(?ContainerInterface $container = null): void
    {
        $this->container = $container;
    }

    public function preUp(Schema $schema): void
    {
        $this->skipInvalidDB();
    }

    public function preDown(Schema $schema): void
    {
        $this->skipInvalidDB();
    }

    private function getEntityManager(): EntityManager
    {
        $name = DbpRelayFormalizeExtension::FORMALIZE_ENTITY_MANAGER_ID;
        $res = $this->container->get("doctrine.orm.{$name}_entity_manager");
        assert($res instanceof EntityManager);

        return $res;
    }

    private function skipInvalidDB()
    {
        $em = DbpRelayFormalizeExtension::FORMALIZE_ENTITY_MANAGER_ID;
        $this->skipIf($this->connection !== $this->getEntityManager()->getConnection(), "Migration can't be executed on this connection, use --em={$em} to select the right one.'");
    }

    protected static function createBinaryUuidForInsertStatement(): string
    {
        return '0x'.str_replace('-', '', Uuid::uuid7()->toString());
    }
}
