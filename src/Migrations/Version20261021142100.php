<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;

class Version20261021142100 extends EntityManagerMigration
{
    public function getDescription(): string
    {
        return 'Alter table formalize_localized_form_names to cascade delete on form deletion';
    }

    public function up(Schema $schema): void
    {
        // first delete all orphaned entries in formalize_localized_form_names:
        $this->addSql('
           DELETE f_n FROM formalize_localized_form_names f_n
           LEFT JOIN formalize_forms f ON f_n.form_identifier = f.identifier
           WHERE f.identifier IS NULL
         ');

        $this->addSql('ALTER TABLE formalize_localized_form_names
            ADD CONSTRAINT fk_formalize_localized_form_names_formalize_forms FOREIGN KEY (form_identifier) REFERENCES formalize_forms(identifier) ON DELETE CASCADE');
    }
}
