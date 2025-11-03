<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;

class Version20251103100600 extends EntityManagerMigration
{
    public function getDescription(): string
    {
        return 'add column last_modified_by_id to submission table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE formalize_submissions ADD last_modified_by_id VARCHAR(50)');
    }
}
