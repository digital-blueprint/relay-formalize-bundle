<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;

class Version20260402100000 extends EntityManagerMigration
{
    public function getDescription(): string
    {
        return 'Add column additional_data to formalize_forms table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE formalize_forms ADD additional_data JSON DEFAULT NULL');
    }
}
