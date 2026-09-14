<?php

declare(strict_types=1);

namespace App\Domain\Questionnaire\ValueObject;

use App\Domain\Questionnaire\Exception\InvalidQuestionnaire;

final readonly class MappingSource
{
    private function __construct(
        public MappingSourceType $type,
        public string $reference,
    ) {
        if (trim($this->reference) === '') {
            throw InvalidQuestionnaire::blank('mapping source reference');
        }
    }

    public static function question(string $questionId): self
    {
        return new self(MappingSourceType::Question, $questionId);
    }

    public static function computedField(string $fieldName): self
    {
        return new self(MappingSourceType::ComputedField, $fieldName);
    }

    public function isQuestion(): bool
    {
        return $this->type === MappingSourceType::Question;
    }
}
