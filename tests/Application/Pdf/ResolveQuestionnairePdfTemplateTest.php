<?php

declare(strict_types=1);

namespace App\Tests\Application\Pdf;

use App\Application\Pdf\ResolveQuestionnairePdfTemplate;
use App\Domain\Filesystem\Contract\FileStorageInterface;
use App\Domain\Questionnaire\Entity\Questionnaire;
use App\Domain\Questionnaire\ValueObject\FormType;
use App\Tests\Support\InMemoryQuestionnaireRepository;
use PHPUnit\Framework\TestCase;

final class ResolveQuestionnairePdfTemplateTest extends TestCase
{
    public function testItReturnsNullWhenTheTemplateFileIsMissing(): void
    {
        $questionnaire = Questionnaire::create('q-1', '1040-NR', FormType::Form1040Nr);

        $path = (new ResolveQuestionnairePdfTemplate(
            InMemoryQuestionnaireRepository::with($questionnaire),
            $this->storage(false),
            sys_get_temp_dir(),
        ))->execute('q-1');

        self::assertNull($path);
    }

    public function testItReturnsTheAbsolutePathWhenReadable(): void
    {
        $questionnaire = Questionnaire::create('q-1', '1040-NR', FormType::Form1040Nr);
        $templates = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sprintax-pdf-templates-'.bin2hex(random_bytes(4));

        $path = (new ResolveQuestionnairePdfTemplate(
            InMemoryQuestionnaireRepository::with($questionnaire),
            $this->storage(true),
            $templates,
        ))->execute('q-1');

        self::assertSame($templates.DIRECTORY_SEPARATOR.'1040-nr.pdf', $path);
    }

    private function storage(bool $readable): FileStorageInterface
    {
        return new class($readable) implements FileStorageInterface {
            public function __construct(private readonly bool $readable)
            {
            }

            public function exists(string $path): bool
            {
                return $this->readable;
            }

            public function isReadable(string $path): bool
            {
                return $this->readable;
            }

            public function ensureDirectory(string $directory): void
            {
            }
        };
    }
}
