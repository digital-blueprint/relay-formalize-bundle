<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;

final class Version20250916101900 extends EntityManagerMigration
{
    public function getDescription(): string
    {
        return 'Add column "tags" to submission table and "available_tags" to form table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE formalize_submissions ADD COLUMN tags JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE formalize_forms ADD COLUMN available_tags JSON DEFAULT NULL');
    }
}
