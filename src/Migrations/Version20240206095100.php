<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;

class Version20240206095100 extends EntityManagerMigration
{
    public function getDescription(): string
    {
        return 'add forms columns for authorization and submissions column for creator ID';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE formalize_forms ADD creator_id VARCHAR(50), ADD read_form_users_option VARCHAR(50) NOT NULL DEFAULT \'authorized\', ADD write_form_users_option VARCHAR(50) NOT NULL DEFAULT \'authorized\', ADD add_submissions_users_option VARCHAR(50) NOT NULL DEFAULT \'authorized\', ADD read_submissions_users_option VARCHAR(50) NOT NULL DEFAULT \'authorized\', ADD write_submissions_users_option VARCHAR(50) NOT NULL DEFAULT \'authorized\'');
        $this->addSql('ALTER TABLE formalize_submissions ADD creator_id VARCHAR(50)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE formalize_forms DROP creator_id, DROP read_form_users_option, DROP write_form_users_option, DROP add_submissions_users_option, DROP read_submissions_users_option, DROP write_submissions_users_option');
        $this->addSql('ALTER TABLE formalize_submissions DROP creator_id');
    }
}
