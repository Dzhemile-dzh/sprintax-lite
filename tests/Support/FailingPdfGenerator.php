<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Domain\Pdf\Contract\PdfGeneratorInterface;
use App\Domain\Pdf\DTO\PdfGenerationRequest;
use App\Domain\Pdf\Exception\PdfGenerationFailed;
use RuntimeException;

final class FailingPdfGenerator implements PdfGeneratorInterface
{
    public function generate(PdfGenerationRequest $request): void
    {
        throw PdfGenerationFailed::overlayFailed($request->sourcePdfPath, new RuntimeException('overlay boom'));
    }
}
