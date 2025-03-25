<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;

final class Version20250317111900 extends EntityManagerMigration
{
    public function getDescription(): string
    {
        return 'Create table formalize_submitted_files';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE formalize_submitted_files (identifier VARCHAR(50) NOT NULL, submission_identifier VARCHAR(50) NOT NULL, file_attribute_name VARCHAR(128) NOT NULL, file_data_identifier VARCHAR(50) NOT NULL, PRIMARY KEY(identifier)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE formalize_submitted_files ADD FOREIGN KEY (submission_identifier) REFERENCES formalize_submissions(identifier) ON DELETE CASCADE ON UPDATE CASCADE');
    }

    public function down(Schema $schema): void
    {
    }
}
