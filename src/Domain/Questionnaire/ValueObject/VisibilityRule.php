<?php

declare(strict_types=1);

namespace App\Domain\Questionnaire\ValueObject;

final readonly class VisibilityRule
{
    /**
     * @param list<VisibilityCondition> $conditions
     */
    public function __construct(
        public array $conditions = [],
    ) {
    }

    public static function alwaysVisible(): self
    {
        return new self([]);
    }

    public function isAlwaysVisible(): bool
    {
        return $this->conditions === [];
    }
}
