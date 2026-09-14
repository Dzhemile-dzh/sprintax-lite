<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Persistence;

use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

abstract class DatabaseTestCase extends KernelTestCase
{
    protected EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;

        $connection = $this->entityManager->getConnection();
        $sqlite = $connection->getDatabasePlatform() instanceof SQLitePlatform;
        if ($sqlite) {
            $connection->executeStatement('PRAGMA foreign_keys = OFF');
        }

        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($this->entityManager);

        if ($metadata !== []) {
            $schemaTool->dropSchema($metadata);
            $schemaTool->createSchema($metadata);
        }

        if ($sqlite) {
            $connection->executeStatement('PRAGMA foreign_keys = ON');
        }
    }

    protected function tearDown(): void
    {
        $this->entityManager->close();

        parent::tearDown();
    }
}
