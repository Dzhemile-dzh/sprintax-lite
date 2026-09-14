<?php

declare(strict_types=1);

namespace App\Domain\Questionnaire\ValueObject;

use App\Domain\Questionnaire\Exception\InvalidQuestionnaire;

final readonly class VisibilityCondition
{
    public function __construct(
        public string $questionKey,
        public VisibilityOperator $operator,
        public string $expectedValue,
    ) {
        if (trim($this->questionKey) === '') {
            throw InvalidQuestionnaire::blank('visibility condition question key');
        }
    }
}
