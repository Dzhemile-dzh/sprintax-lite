<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260914235300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Record when a submission PDF has been emailed to the client';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE questionnaire_submission ADD COLUMN pdf_emailed_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE questionnaire_submission DROP COLUMN pdf_emailed_at');
    }
}
