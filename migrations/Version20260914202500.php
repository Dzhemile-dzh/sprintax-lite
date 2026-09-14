<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds a stable form type used by calculators and PDF templates.
 */
final class Version20260914202500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add form_type to questionnaire';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('PRAGMA foreign_keys = OFF');
        $this->addSql('CREATE TEMPORARY TABLE __temp__questionnaire AS SELECT id, name, description FROM questionnaire');
        $this->addSql('DROP TABLE questionnaire');
        $this->addSql(<<<'SQL'
            CREATE TABLE questionnaire (
              id VARCHAR(64) NOT NULL,
              name VARCHAR(255) NOT NULL,
              description CLOB DEFAULT NULL,
              form_type VARCHAR(32) NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql("INSERT INTO questionnaire (id, name, description, form_type) SELECT id, name, description, '1040-nr' FROM __temp__questionnaire");
        $this->addSql('DROP TABLE __temp__questionnaire');
        $this->addSql('PRAGMA foreign_keys = ON');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('PRAGMA foreign_keys = OFF');
        $this->addSql('CREATE TEMPORARY TABLE __temp__questionnaire AS SELECT id, name, description FROM questionnaire');
        $this->addSql('DROP TABLE questionnaire');
        $this->addSql(<<<'SQL'
            CREATE TABLE questionnaire (
              id VARCHAR(64) NOT NULL,
              name VARCHAR(255) NOT NULL,
              description CLOB DEFAULT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('INSERT INTO questionnaire (id, name, description) SELECT id, name, description FROM __temp__questionnaire');
        $this->addSql('DROP TABLE __temp__questionnaire');
        $this->addSql('PRAGMA foreign_keys = ON');
    }
}
