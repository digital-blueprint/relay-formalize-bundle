<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;

class Version20240514085800 extends EntityManagerMigration
{
    public function getDescription(): string
    {
        return 'remove form columns of old authorization model; add foreign key constraint for submissions form identifiers';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE formalize_forms
            DROP read_form_users_option,
            DROP write_form_users_option,
            DROP add_submissions_users_option,
            DROP read_submissions_users_option,
            DROP write_submissions_users_option');
        $this->addSql('ALTER TABLE formalize_submissions CHANGE form form_identifier VARCHAR(50)');
        $this->addSql('ALTER TABLE formalize_forms CONVERT TO CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE formalize_submissions ADD CONSTRAINT FK_D819EFC5147F90EA FOREIGN KEY (form_identifier) REFERENCES formalize_forms (identifier)');
    }

    public function down(Schema $schema): void
    {
    }
}
