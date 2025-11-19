<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Migrations;

use Dbp\Relay\FormalizeBundle\Entity\Form;
use Doctrine\DBAL\Schema\Schema;

class Version20251118095300 extends EntityManagerMigration
{
    public function getDescription(): string
    {
        return 'add column tag_permissions_for_submitters to formalize_forms table';
    }

    public function up(Schema $schema): void
    {
        $tagPermissionsRead = Form::TAG_PERMISSIONS_READ;
        $this->addSql("ALTER TABLE formalize_forms ADD tag_permissions_for_submitters SMALLINT DEFAULT $tagPermissionsRead NOT NULL");
    }
}
