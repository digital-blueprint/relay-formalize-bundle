<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;

/**
 * Rename formalize_submissions.
 */
final class Version20231220013380 extends EntityManagerMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('INSERT INTO formalize_forms (identifier, name, date_created) SELECT DISTINCT form, form, CURRENT_TIMESTAMP FROM formalize_submissions');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM formalize_forms');
    }
}
