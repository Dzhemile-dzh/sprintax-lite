<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Creates tables for the questionnaire, submission, and user aggregates.
 */
final class Version20260914092233 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create questionnaire, submission, and user tables';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE app_user (
              id VARCHAR(64) NOT NULL,
              email VARCHAR(180) NOT NULL,
              password_hash VARCHAR(255) NOT NULL,
              role VARCHAR(32) NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_app_user_email ON app_user (email)');
        $this->addSql(<<<'SQL'
            CREATE TABLE questionnaire (
              id VARCHAR(64) NOT NULL,
              name VARCHAR(255) NOT NULL,
              description CLOB DEFAULT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE questionnaire_step (
              id VARCHAR(64) NOT NULL,
              title VARCHAR(255) NOT NULL,
              position INTEGER NOT NULL,
              questionnaire_id VARCHAR(64) NOT NULL,
              PRIMARY KEY (id),
              CONSTRAINT FK_1721B195CE07E8FF FOREIGN KEY (questionnaire_id) REFERENCES questionnaire (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_questionnaire_step_position ON questionnaire_step (questionnaire_id, position)
        SQL);
        $this->addSql('CREATE INDEX IDX_1721B195CE07E8FF ON questionnaire_step (questionnaire_id)');
        $this->addSql(<<<'SQL'
            CREATE TABLE question (
              id VARCHAR(64) NOT NULL,
              "key" VARCHAR(100) NOT NULL,
              label VARCHAR(255) NOT NULL,
              type VARCHAR(32) NOT NULL,
              position INTEGER NOT NULL,
              help_text CLOB DEFAULT NULL,
              validation CLOB NOT NULL,
              visibility CLOB NOT NULL,
              step_id VARCHAR(64) NOT NULL,
              questionnaire_id VARCHAR(64) NOT NULL,
              PRIMARY KEY (id),
              CONSTRAINT FK_B6F7494E73B21E9C FOREIGN KEY (step_id) REFERENCES questionnaire_step (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE,
              CONSTRAINT FK_B6F7494ECE07E8FF FOREIGN KEY (questionnaire_id) REFERENCES questionnaire (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_question_step_position ON question (step_id, position)');
        $this->addSql('CREATE UNIQUE INDEX uniq_question_questionnaire_key ON question (questionnaire_id, "key")');
        $this->addSql('CREATE INDEX IDX_B6F7494E73B21E9C ON question (step_id)');
        $this->addSql(<<<'SQL'
            CREATE TABLE question_option (
              id VARCHAR(64) NOT NULL,
              label VARCHAR(255) NOT NULL,
              value VARCHAR(100) NOT NULL,
              position INTEGER NOT NULL,
              question_id VARCHAR(64) NOT NULL,
              PRIMARY KEY (id),
              CONSTRAINT FK_5DDB2FB81E27F6BF FOREIGN KEY (question_id) REFERENCES question (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_question_option_value ON question_option (question_id, value)');
        $this->addSql('CREATE UNIQUE INDEX uniq_question_option_position ON question_option (question_id, position)');
        $this->addSql('CREATE INDEX IDX_5DDB2FB81E27F6BF ON question_option (question_id)');
        $this->addSql(<<<'SQL'
            CREATE TABLE question_mapping (
              id VARCHAR(64) NOT NULL,
              source_type VARCHAR(32) NOT NULL,
              source_reference VARCHAR(100) NOT NULL,
              coordinates CLOB NOT NULL,
              questionnaire_id VARCHAR(64) NOT NULL,
              PRIMARY KEY (id),
              CONSTRAINT FK_473AF997CE07E8FF FOREIGN KEY (questionnaire_id) REFERENCES questionnaire (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_question_mapping_source ON question_mapping (questionnaire_id, source_type, source_reference)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE questionnaire_submission (
              id VARCHAR(64) NOT NULL,
              status VARCHAR(32) NOT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME NOT NULL,
              finalized_at DATETIME DEFAULT NULL,
              questionnaire_id VARCHAR(64) NOT NULL,
              user_id VARCHAR(64) NOT NULL,
              current_step_id VARCHAR(64) NOT NULL,
              PRIMARY KEY (id),
              CONSTRAINT FK_2CF49A6ACE07E8FF FOREIGN KEY (questionnaire_id) REFERENCES questionnaire (id) NOT DEFERRABLE INITIALLY IMMEDIATE,
              CONSTRAINT FK_2CF49A6AA76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) NOT DEFERRABLE INITIALLY IMMEDIATE,
              CONSTRAINT FK_2CF49A6AD9BF9B19 FOREIGN KEY (current_step_id) REFERENCES questionnaire_step (id) NOT DEFERRABLE INITIALLY IMMEDIATE
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_submission_user_questionnaire ON questionnaire_submission (user_id, questionnaire_id)');
        $this->addSql('CREATE INDEX idx_submission_questionnaire ON questionnaire_submission (questionnaire_id)');
        $this->addSql('CREATE INDEX IDX_2CF49A6AD9BF9B19 ON questionnaire_submission (current_step_id)');
        $this->addSql(<<<'SQL'
            CREATE TABLE submission_answer (
              value CLOB NOT NULL,
              submission_id VARCHAR(64) NOT NULL,
              question_id VARCHAR(64) NOT NULL,
              PRIMARY KEY (submission_id, question_id),
              CONSTRAINT FK_E2D8179BE1FD4933 FOREIGN KEY (submission_id) REFERENCES questionnaire_submission (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE,
              CONSTRAINT FK_E2D8179B1E27F6BF FOREIGN KEY (question_id) REFERENCES question (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_E2D8179BE1FD4933 ON submission_answer (submission_id)');
        $this->addSql('CREATE INDEX IDX_E2D8179B1E27F6BF ON submission_answer (question_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE submission_answer');
        $this->addSql('DROP TABLE questionnaire_submission');
        $this->addSql('DROP TABLE question_mapping');
        $this->addSql('DROP TABLE question_option');
        $this->addSql('DROP TABLE question');
        $this->addSql('DROP TABLE questionnaire_step');
        $this->addSql('DROP TABLE questionnaire');
        $this->addSql('DROP TABLE app_user');
    }
}
