<?php

declare(strict_types=1);

namespace App\Domain\Calculation\DTO;

final readonly class CalculationInput
{
    /**
     * @param array<string, mixed> $answers
     */
    public function __construct(
        public string $formType,
        public array $answers,
    ) {
    }
}
