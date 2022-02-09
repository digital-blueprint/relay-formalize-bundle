<?php

declare(strict_types=1);

namespace Dbp\Relay\FormsBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create table forms_form_data.
 */
final class Version20220202170800 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE forms_form_data (identifier VARCHAR(50) NOT NULL, data TEXT NOT NULL, created DATETIME NOT NULL, PRIMARY KEY(identifier)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE forms_form_data');
    }
}
