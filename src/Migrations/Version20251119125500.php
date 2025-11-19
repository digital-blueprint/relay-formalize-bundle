<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;

class Version20251119125500 extends EntityManagerMigration
{
    public function getDescription(): string
    {
        return 'update column available_tags values to be an array of objects instead of strings, where the original string is stored in the identifier property of the object';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<END
            UPDATE formalize_forms
            JOIN (
            SELECT identifier, JSON_ARRAYAGG(JSON_OBJECT('identifier', jt.tag)) AS new_tags
            FROM formalize_forms
            JOIN JSON_TABLE(available_tags, "$[*]" COLUMNS( tag VARCHAR(4000) PATH "$" )) AS jt GROUP BY identifier) AS sub USING (identifier)
            SET formalize_forms.available_tags = sub.new_tags;
            END
        );
    }
}
