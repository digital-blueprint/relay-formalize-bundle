<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add key "idxForm" to "formalize_submission".
 */
final class Version20220216150049 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE formalize_submission ADD KEY idxForm (form)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP index idxForm ON formalize_submission');
    }
}
