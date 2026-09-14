<?php

declare(strict_types=1);

namespace App\Domain\Questionnaire\ValueObject;

use App\Domain\Questionnaire\Exception\InvalidQuestionnaire;

final readonly class QuestionValidation
{
    public function __construct(
        public bool $required = false,
        public ?int $min = null,
        public ?int $max = null,
        public ?string $regex = null,
    ) {
        if ($this->min !== null && $this->max !== null && $this->min > $this->max) {
            throw InvalidQuestionnaire::invalidValidationRange();
        }

        if ($this->regex !== null && trim($this->regex) === '') {
            throw InvalidQuestionnaire::blank('validation regex');
        }
    }

    public static function none(): self
    {
        return new self();
    }

    public static function required(): self
    {
        return new self(required: true);
    }
}
