<?php

declare(strict_types=1);

namespace App\Domain\Audit\DTO;

final readonly class ConditionSnapshot
{
    public function __construct(
        public string $questionKey,
        public string $operator,
        public string $expectedValue,
    ) {
    }

    public function describe(): string
    {
        return sprintf('%s %s "%s"', $this->questionKey, str_replace('_', ' ', $this->operator), $this->expectedValue);
    }
}
