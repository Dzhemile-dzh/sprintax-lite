<?php

declare(strict_types=1);

namespace App\Domain\Audit\DTO;

final readonly class StepSnapshot
{
    /**
     * @param list<QuestionSnapshot> $questions
     */
    public function __construct(
        public string $title,
        public int $position,
        public array $questions,
    ) {
    }
}
