<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Stores the generated PDF path on finalized submissions.
 */
final class Version20260914154900 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add pdf_path to questionnaire_submission';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE questionnaire_submission ADD COLUMN pdf_path VARCHAR(512) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE questionnaire_submission DROP COLUMN pdf_path');
    }
}
