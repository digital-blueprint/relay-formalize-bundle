<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;

class Version20240206135300 extends EntityManagerMigration
{
    public function getDescription(): string
    {
        return 'set deprecated \'client_scope\' user authorization option for existing forms';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE formalize_forms SET read_submissions_users_option = \'client_scope\', add_submissions_users_option = \'client_scope\'');
    }

    public function down(Schema $schema): void
    {
    }
}
