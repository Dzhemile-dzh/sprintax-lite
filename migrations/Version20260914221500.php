<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * PDF question mappings store the question key instead of the opaque question id.
 */
final class Version20260914221500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store PDF question mappings by question key';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE question_mapping
            SET source_reference = (
                SELECT q.key FROM question q WHERE q.id = question_mapping.source_reference
            )
            WHERE source_type = 'question'
              AND EXISTS (
                  SELECT 1 FROM question q WHERE q.id = question_mapping.source_reference
              )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE question_mapping
            SET source_reference = (
                SELECT q.id FROM question q WHERE q.key = question_mapping.source_reference
                  AND q.questionnaire_id = question_mapping.questionnaire_id
            )
            WHERE source_type = 'question'
              AND EXISTS (
                  SELECT 1 FROM question q
                  WHERE q.key = question_mapping.source_reference
                    AND q.questionnaire_id = question_mapping.questionnaire_id
              )
        SQL);
    }
}
