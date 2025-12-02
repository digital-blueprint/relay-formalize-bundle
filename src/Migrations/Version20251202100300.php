<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Migrations;

use Dbp\Relay\FormalizeBundle\Entity\LocalizedFormName;
use Doctrine\DBAL\Schema\Schema;

class Version20251202100300 extends EntityManagerMigration
{
    public function getDescription(): string
    {
        return 'add table formalize_localized_form_names';
    }

    public function up(Schema $schema): void
    {
        $tableName = LocalizedFormName::TABLE_NAME;
        $formIdentifierColumnName = LocalizedFormName::FORM_IDENTIFIER_COLUMN_NAME;
        $languageTagColumnName = LocalizedFormName::LANGUAGE_TAG_COLUMN_NAME;
        $nameColumnName = LocalizedFormName::NAME_COLUMN_NAME;

        $this->addSql("CREATE TABLE $tableName (
            $formIdentifierColumnName VARCHAR(36) NOT NULL,
            $languageTagColumnName VARCHAR(2) NOT NULL,
            $nameColumnName VARCHAR(128) NOT NULL,
            PRIMARY KEY($formIdentifierColumnName, $languageTagColumnName)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
}
