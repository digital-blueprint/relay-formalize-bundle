<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;

class Version20240516102600 extends EntityManagerMigration
{
    public function getDescription(): string
    {
        return 'replace submission table foreign key constraint by foreign key with cascade delete';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE formalize_submissions DROP CONSTRAINT FK_D819EFC5147F90EA');
        $this->addSql('ALTER TABLE formalize_submissions ADD FOREIGN KEY (form_identifier) REFERENCES formalize_forms(identifier) ON DELETE CASCADE ON UPDATE CASCADE');
    }

    public function down(Schema $schema): void
    {
    }
}
