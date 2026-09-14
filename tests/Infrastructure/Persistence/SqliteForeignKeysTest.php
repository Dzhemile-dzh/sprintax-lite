<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Persistence;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManagerInterface;
use DoctrineMigrations\Version20260914092233;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SqliteForeignKeysTest extends KernelTestCase
{
    public function testAppConnectionEnablesSqliteForeignKeys(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        $foreignKeys = $entityManager->getConnection()->fetchOne('PRAGMA foreign_keys');
        self::assertContains($foreignKeys, [1, '1'], 'SQLite foreign_keys should be enabled.');
    }

    public function testInitialMigrationAppliesWithForeignKeysEnabled(): void
    {
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);
        $connection->executeStatement('PRAGMA foreign_keys = ON');

        require_once dirname(__DIR__, 3).'/migrations/Version20260914092233.php';

        $migration = new Version20260914092233($connection, new NullLogger());
        $migration->up(new Schema());

        foreach ($migration->getSql() as $query) {
            $connection->executeStatement($query->getStatement());
        }

        $schemaManager = $connection->createSchemaManager();
        $foreignKeys = $connection->fetchOne('PRAGMA foreign_keys');

        self::assertContains($foreignKeys, [1, '1'], 'SQLite foreign_keys should be enabled.');
        self::assertTrue($schemaManager->tablesExist([
            'app_user',
            'questionnaire',
            'questionnaire_step',
            'question',
            'question_option',
            'question_mapping',
            'questionnaire_submission',
            'submission_answer',
        ]));
    }
}
