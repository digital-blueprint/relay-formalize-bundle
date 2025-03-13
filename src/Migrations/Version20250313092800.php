<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;

final class Version20250313092800 extends EntityManagerMigration
{
    public function getDescription(): string
    {
        return 'Add formalize_form max_num_submissions_per_creator and formalize_submission date_last_modified';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE formalize_forms ADD max_num_submissions_per_creator SMALLINT DEFAULT 10 NOT NULL ');
        $this->addSql('ALTER TABLE formalize_submissions ADD date_last_modified DATETIME NOT NULL');
    }

    public function down(Schema $schema): void
    {
    }
}
