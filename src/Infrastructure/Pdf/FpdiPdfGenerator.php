<?php

declare(strict_types=1);

namespace App\Infrastructure\Pdf;

use App\Domain\Pdf\Contract\PdfGeneratorInterface;
use App\Domain\Pdf\DTO\PdfGenerationRequest;
use LogicException;

final class FpdiPdfGenerator implements PdfGeneratorInterface
{
    public function generate(PdfGenerationRequest $request): void
    {
        throw new LogicException(sprintf(
            'PDF generation is not implemented yet for "%s".',
            $request->sourcePdfPath,
        ));
    }
}
