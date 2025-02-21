<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Migrations;

use Dbp\Relay\FormalizeBundle\Common\DemoForm;
use Doctrine\DBAL\Schema\Schema;

final class Version20241218144500 extends EntityManagerMigration
{
    public function getDescription(): string
    {
        return 'Create the demo form.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('INSERT INTO formalize_forms (identifier, name, date_created) VALUES ("'.DemoForm::FORM_IDENTIFIER.'", "Demo Form", CURRENT_TIMESTAMP)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM formalize_forms WHERE identifier = "'.DemoForm::FORM_IDENTIFIER.'"');
    }
}
