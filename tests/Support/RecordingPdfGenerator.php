<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Domain\Pdf\Contract\PdfGeneratorInterface;
use App\Domain\Pdf\DTO\PdfGenerationRequest;

final class RecordingPdfGenerator implements PdfGeneratorInterface
{
    public ?PdfGenerationRequest $last = null;

    public int $calls = 0;

    public function generate(PdfGenerationRequest $request): void
    {
        ++$this->calls;
        $this->last = $request;

        $directory = dirname($request->outputPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents($request->outputPath, '%PDF-1.4');
    }
}
