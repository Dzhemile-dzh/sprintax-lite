<?php

declare(strict_types=1);

namespace App\Domain\Audit\DTO;

final readonly class StructureSnapshot
{
    /**
     * @param list<StepSnapshot> $steps
     * @param list<MappingSnapshot> $mappings
     */
    public function __construct(
        public string $name,
        public string $formType,
        public ?string $description,
        public array $steps,
        public array $mappings,
    ) {
    }

    public function questionCount(): int
    {
        $count = 0;

        foreach ($this->steps as $step) {
            $count += count($step->questions);
        }

        return $count;
    }
}
