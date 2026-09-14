<?php

declare(strict_types=1);

namespace App\Domain\Questionnaire\Entity;

use App\Domain\Questionnaire\Exception\InvalidQuestionnaire;
use App\Domain\Questionnaire\ValueObject\PdfCoordinates;
use App\Domain\Questionnaire\ValueObject\MappingSource;

final class QuestionMapping
{
    private function __construct(
        private string $id,
        private MappingSource $source,
        private PdfCoordinates $coordinates,
    ) {
        if (trim($this->id) === '') {
            throw InvalidQuestionnaire::blank('mapping id');
        }
    }

    public static function forQuestion(
        string $id,
        string $questionId,
        PdfCoordinates $coordinates,
    ): self {
        return new self($id, MappingSource::question($questionId), $coordinates);
    }

    public static function forComputedField(
        string $id,
        string $fieldName,
        PdfCoordinates $coordinates,
    ): self {
        return new self($id, MappingSource::computedField($fieldName), $coordinates);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function source(): MappingSource
    {
        return $this->source;
    }

    public function coordinates(): PdfCoordinates
    {
        return $this->coordinates;
    }
}
