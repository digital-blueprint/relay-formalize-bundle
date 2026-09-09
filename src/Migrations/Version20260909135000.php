<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;

class Version20260909135000 extends EntityManagerMigration
{
    public function getDescription(): string
    {
        return 'Increase length of the name field in LocalizedFormName entity to 256 characters';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE formalize_localized_form_names MODIFY name VARCHAR(256)');
    }
}
