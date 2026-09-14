<?php

declare(strict_types=1);

namespace App\Application\Pdf;

use App\Domain\Submission\Exception\InvalidSubmission;

final class PdfOutputPath
{
    public static function fileName(string $submissionId): string
    {
        self::assertSafeId($submissionId);

        return $submissionId.'.pdf';
    }

    public static function absolute(string $outputDirectory, string $submissionId): string
    {
        return $outputDirectory.DIRECTORY_SEPARATOR.self::fileName($submissionId);
    }

    private static function assertSafeId(string $submissionId): void
    {
        $hexId = preg_match('/^[a-f0-9]{32}$/', $submissionId) === 1;
        $pathSafeId = preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/', $submissionId) === 1;

        if ($submissionId !== basename($submissionId) || (!$hexId && !$pathSafeId)) {
            throw InvalidSubmission::unsafePdfPath();
        }
    }
}
