<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Migrations;

use Dbp\Relay\FormalizeBundle\Authorization\AuthorizationService;
use Dbp\Relay\FormalizeBundle\Entity\Form;
use Doctrine\DBAL\Schema\Schema;

class Version20260720144100 extends EntityManagerMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE '.Form::TABLE_NAME.' 
            ADD role_identifier_when_draft BINARY(16) NOT NULL DEFAULT '.
            self::uuidToHexString(AuthorizationService::EDITOR_WITH_DELETE_ROLE_IDENTIFIER).', '.
            'ADD role_identifier_when_submitted BINARY(16) NULL DEFAULT '.
            self::uuidToHexString(AuthorizationService::READER_ROLE_IDENTIFIER));
    }
}
