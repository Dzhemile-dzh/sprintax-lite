<?php

declare(strict_types=1);

namespace App\Domain\Audit\DTO;

final readonly class MappingSnapshot
{
    public function __construct(
        public string $sourceType,
        public string $sourceReference,
        public int $page,
        public float $xMm,
        public float $yMm,
        public ?int $fontSize,
    ) {
    }
}
