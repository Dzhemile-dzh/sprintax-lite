<?php

declare(strict_types=1);

namespace App\Domain\Audit\DTO;

final readonly class OptionSnapshot
{
    public function __construct(
        public string $label,
        public string $value,
        public int $position,
    ) {
    }
}
