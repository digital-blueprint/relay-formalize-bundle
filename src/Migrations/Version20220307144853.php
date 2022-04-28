<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;

/**
 * Rename formalize_submissions.
 */
final class Version20220307144853 extends EntityManagerMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('RENAME TABLE formalize_submission TO formalize_submissions');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('RENAME TABLE formalize_submissions TO formalize_submission');
    }
}
