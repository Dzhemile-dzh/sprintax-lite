<?php

declare(strict_types=1);

namespace App\Domain\Pdf\DTO;

final readonly class PdfFieldPlacement
{
    public function __construct(
        public int $page,
        public float $xMm,
        public float $yMm,
        public ?int $fontSize = null,
        public bool $amountColumn = false,
    ) {
    }
}
