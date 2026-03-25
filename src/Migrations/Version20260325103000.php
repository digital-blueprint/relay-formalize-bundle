<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;

class Version20260325103000 extends EntityManagerMigration
{
    public function getDescription(): string
    {
        return 'add column frontend_key to formalize_forms table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE formalize_forms ADD frontend_key VARCHAR(50) DEFAULT NULL');
    }
}
