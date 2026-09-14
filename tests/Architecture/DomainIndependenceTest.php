<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class DomainIndependenceTest extends TestCase
{
    public function testDomainSourceDoesNotDependOnSymfonyOrDoctrineRuntime(): void
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

            preg_match_all('/\bDoctrine\\\\[A-Za-z0-9_\\\\]+/', $contents, $matches);

            foreach ($matches[0] as $reference) {
                $allowed = str_starts_with($reference, 'Doctrine\\ORM\\Mapping')
                    || str_starts_with($reference, 'Doctrine\\Common\\Collections');

                if (!$allowed) {
                    $violations[] = $file->getPathname().' references '.$reference;
                }
            }

            if (str_contains($contents, 'Symfony\\')) {
                $violations[] = $file->getPathname().' references Symfony';
            }
        }

        self::assertSame(
            [],
            $violations,
            'Domain may use Doctrine mapping/collections only; no Symfony or Doctrine runtime APIs.',
        );
    }
}
