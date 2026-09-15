<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260915090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Record an append-only trail of questionnaire structure versions';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE questionnaire_revision (
                id VARCHAR(64) NOT NULL,
                questionnaire_id VARCHAR(64) NOT NULL,
                version INTEGER NOT NULL,
                action VARCHAR(32) NOT NULL,
                summary CLOB NOT NULL,
                actor_id VARCHAR(64) DEFAULT NULL,
                actor_email VARCHAR(180) DEFAULT NULL,
                recorded_at DATETIME NOT NULL,
                snapshot CLOB NOT NULL,
                PRIMARY KEY(id)
            )
            SQL);
        $this->addSql(
            'CREATE UNIQUE INDEX uniq_questionnaire_revision_version ON questionnaire_revision (questionnaire_id, version)',
        );
        $this->addSql('CREATE INDEX idx_questionnaire_revision_recorded_at ON questionnaire_revision (recorded_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE questionnaire_revision');
    }
}
