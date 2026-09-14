<?php

declare(strict_types=1);

namespace App\Domain\Pdf\Exception;

use RuntimeException;
use Throwable;

final class PdfGenerationFailed extends RuntimeException
{
    public static function templateMissing(string $formType, string $expectedPath): self
    {
        return new self(sprintf(
            'No PDF template for form "%s" at "%s".',
            $formType,
            $expectedPath,
        ));
    }

    public static function sourceUnreadable(string $path): self
    {
        return new self(sprintf('PDF template "%s" cannot be read.', $path));
    }

    public static function pageOutOfRange(int $page, int $pageCount): self
    {
        return new self(sprintf(
            'PDF mapping page %d is outside the template (%d pages).',
            $page,
            $pageCount,
        ));
    }

    public static function unknownQuestion(string $questionId): self
    {
        return new self(sprintf('PDF mapping references unknown question "%s".', $questionId));
    }

    public static function writeFailed(string $path, Throwable $previous): self
    {
        return new self(sprintf('Failed to write generated PDF to "%s".', $path), 0, $previous);
    }

    public static function overlayFailed(string $path, Throwable $previous): self
    {
        return new self(sprintf('Failed to overlay fields onto "%s".', $path), 0, $previous);
    }
}
