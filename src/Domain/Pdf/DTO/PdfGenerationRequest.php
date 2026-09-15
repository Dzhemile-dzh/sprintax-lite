<?php

declare(strict_types=1);

namespace App\Domain\Pdf\DTO;

final readonly class PdfGenerationRequest
{
    /**
     * @param list<array{placement: PdfFieldPlacement, value: string}> $fields
     * @param list<int> $mappedPages
     */
    public function __construct(
        public string $sourcePdfPath,
        public string $outputPath,
        public array $fields,
        public array $mappedPages = [],
    ) {
    }
}
