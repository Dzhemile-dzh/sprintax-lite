<?php

declare(strict_types=1);

namespace App\Domain\Calculation\DTO;

final readonly class CalculationResult
{
    /**
     * @param array<string, int|float|string> $values
     */
    public function __construct(
        public array $values,
    ) {
    }
}
