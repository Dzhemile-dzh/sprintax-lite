<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class DomainIndependenceTest extends TestCase
{
    public function testDomainSourceDoesNotDependOnSymfonyOrDoctrine(): void
    {
        $domainDirectory = dirname(__DIR__, 2).'/src/Domain';
        $violations = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($domainDirectory),
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            self::assertNotFalse($contents);

            if (preg_match('/^use (Symfony|Doctrine)\\\\/m', $contents) === 1) {
                $violations[] = $file->getPathname();
            }
        }

        self::assertSame(
            [],
            $violations,
            'Domain must not import Symfony or Doctrine classes.',
        );
    }
}
