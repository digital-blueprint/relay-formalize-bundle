<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;

/**
 * Rename formalize_submissions.
 */
final class Version20240125090000 extends EntityManagerMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE formalize_forms ADD data_feed_schema LONGTEXT, ADD availability_starts DATETIME, ADD availability_ends DATETIME');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE formalize_forms DROP data_feed_schema, DROP availability_starts, DROP availability_ends');
    }
}
