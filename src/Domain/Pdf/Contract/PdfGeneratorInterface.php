<?php

declare(strict_types=1);

namespace App\Domain\Pdf\Contract;

use App\Domain\Pdf\DTO\PdfGenerationRequest;

interface PdfGeneratorInterface
{
    public function generate(PdfGenerationRequest $request): void;
}
