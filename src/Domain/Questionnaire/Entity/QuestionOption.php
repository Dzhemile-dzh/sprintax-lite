<?php

declare(strict_types=1);

namespace App\Domain\Questionnaire\Entity;

use App\Domain\Questionnaire\Exception\InvalidQuestionnaire;

final class QuestionOption
{
    private function __construct(
        private string $id,
        private string $label,
        private string $value,
        private int $position,
    ) {
        if (trim($this->id) === '') {
            throw InvalidQuestionnaire::blank('option id');
        }

        if (trim($this->label) === '') {
            throw InvalidQuestionnaire::blank('option label');
        }

        if (trim($this->value) === '') {
            throw InvalidQuestionnaire::blank('option value');
        }

        if ($this->position < 1) {
            throw InvalidQuestionnaire::blank('option position');
        }
    }

    public static function create(string $id, string $label, string $value, int $position): self
    {
        return new self($id, $label, $value, $position);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function position(): int
    {
        return $this->position;
    }
}
