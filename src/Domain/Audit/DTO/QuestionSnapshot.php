<?php

declare(strict_types=1);

namespace App\Domain\Audit\DTO;

final readonly class QuestionSnapshot
{
    /**
     * @param list<OptionSnapshot> $options
     * @param list<ConditionSnapshot> $conditions
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $type,
        public int $position,
        public ?string $helpText,
        public bool $required,
        public ?int $min,
        public ?int $max,
        public ?string $regex,
        public array $options,
        public array $conditions,
    ) {
    }
}
