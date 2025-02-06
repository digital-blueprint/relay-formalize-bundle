<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Migrations;

use Dbp\Relay\FormalizeBundle\Entity\Submission;
use Doctrine\DBAL\Schema\Schema;

final class Version20250204111200 extends EntityManagerMigration
{
    public function getDescription(): string
    {
        return 'Update submissionState attribute';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE formalize_forms CHANGE allowed_submission_states allowed_submission_states SMALLINT DEFAULT '.Submission::SUBMISSION_STATE_SUBMITTED.' NOT NULL');
        $this->addSql('ALTER TABLE formalize_submissions CHANGE submission_state submission_state SMALLINT DEFAULT '.Submission::SUBMISSION_STATE_SUBMITTED.' NOT NULL');

        // Set default for existing forms/submissions
        $this->addSql('UPDATE formalize_forms SET allowed_submission_states = '.Submission::SUBMISSION_STATE_SUBMITTED);
        $this->addSql('UPDATE formalize_submissions SET submission_state = '.Submission::SUBMISSION_STATE_SUBMITTED);

        $this->addSql('ALTER TABLE formalize_forms CHANGE allowed_actions_when_submitted allowed_actions_when_submitted SMALLINT DEFAULT 0');
        $this->addSql('UPDATE formalize_forms SET allowed_actions_when_submitted = 0');
    }

    public function down(Schema $schema): void
    {
    }
}
