<?php

declare(strict_types=1);

namespace App\Domain\Calculation\DTO;

use InvalidArgumentException;

final readonly class CalculationResult
{
    /**
     * @param array<string, int|float|string> $values
     */
    public function __construct(
        public array $values,
    ) {
    }

    public function has(string $field): bool
    {
        return array_key_exists($field, $this->values);
    }

    public function value(string $field): int|float|string
    {
        if (!$this->has($field)) {
            throw new InvalidArgumentException(sprintf('Calculated field "%s" is not present.', $field));
        }

        return $this->values[$field];
    }
}
