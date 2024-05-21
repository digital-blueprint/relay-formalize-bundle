<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;

class Version20240521102900 extends EntityManagerMigration
{
    public function getDescription(): string
    {
        return 'add column submission_level_authorization to formalize_forms table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE formalize_forms ADD COLUMN submission_level_authorization BOOLEAN DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE formalize_forms DROP COLUMN submission_level_authorization');
    }
}
