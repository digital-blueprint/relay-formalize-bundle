<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;

final class Version20250123104800 extends EntityManagerMigration
{
    public function getDescription(): string
    {
        return 'Add new submissionState attributes to form and submission';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE formalize_forms ADD allowed_submission_states SMALLINT DEFAULT 1 NOT NULL ');
        $this->addSql('ALTER TABLE formalize_submissions ADD submission_state SMALLINT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE formalize_submissions CHANGE data_feed_element data_feed_element TEXT DEFAULT NULL'); // make nullable

        // Set default for existing forms/submissions
        $this->addSql('UPDATE formalize_forms SET allowed_submission_states = 1');
        $this->addSql('UPDATE formalize_submissions SET submission_state = 1');
    }

    public function down(Schema $schema): void
    {
    }
}
