<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class WebDatabaseTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
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
}
