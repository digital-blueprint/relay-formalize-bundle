<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;

/**
 * Rename formalize_submissions.
 */
final class Version20231219094100 extends EntityManagerMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE formalize_forms (identifier VARCHAR(50) PRIMARY KEY, name VARCHAR(256) NOT NULL, date_created DATETIME)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE formalize_forms');
    }
}
