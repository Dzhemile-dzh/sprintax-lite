<?php

declare(strict_types=1);

namespace App\Domain\Questionnaire\Entity;

use App\Domain\Questionnaire\Exception\InvalidQuestionnaire;
use App\Domain\Questionnaire\ValueObject\QuestionType;
use App\Domain\Questionnaire\ValueObject\QuestionValidation;
use App\Domain\Questionnaire\ValueObject\VisibilityRule;

final class Question
{
    /** @var list<QuestionOption> */
    private array $options = [];

    private function __construct(
        private string $id,
        private string $key,
        private string $label,
        private QuestionType $type,
        private int $position,
        private ?string $helpText,
        private QuestionValidation $validation,
        private VisibilityRule $visibility,
    ) {
        if (trim($this->id) === '') {
            throw InvalidQuestionnaire::blank('question id');
        }

        if (trim($this->key) === '') {
            throw InvalidQuestionnaire::blank('question key');
        }

        if (trim($this->label) === '') {
            throw InvalidQuestionnaire::blank('question label');
        }

        if ($this->position < 1) {
            throw InvalidQuestionnaire::blank('question position');
        }

        if ($this->helpText !== null && trim($this->helpText) === '') {
            $this->helpText = null;
        }
    }

    public static function create(
        string $id,
        string $key,
        string $label,
        QuestionType $type,
        int $position,
        ?string $helpText = null,
        ?QuestionValidation $validation = null,
        ?VisibilityRule $visibility = null,
    ): self {
        return new self(
            $id,
            $key,
            $label,
            $type,
            $position,
            $helpText,
            $validation ?? QuestionValidation::none(),
            $visibility ?? VisibilityRule::alwaysVisible(),
        );
    }

    public function id(): string
    {
        return $this->id;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function type(): QuestionType
    {
        return $this->type;
    }

    public function position(): int
    {
        return $this->position;
    }

    public function helpText(): ?string
    {
        return $this->helpText;
    }

    public function validation(): QuestionValidation
    {
        return $this->validation;
    }

    public function visibility(): VisibilityRule
    {
        return $this->visibility;
    }

    /**
     * @return list<QuestionOption>
     */
    public function options(): array
    {
        $options = $this->options;
        usort(
            $options,
            static fn (QuestionOption $left, QuestionOption $right): int => $left->position() <=> $right->position(),
        );

        return $options;
    }

    public function addOption(QuestionOption $option): void
    {
        if (!$this->type->allowsOptions()) {
            throw InvalidQuestionnaire::optionsNotAllowed($this->type->value);
        }

        foreach ($this->options as $existing) {
            if ($existing->value() === $option->value()) {
                throw InvalidQuestionnaire::duplicateOptionValue($option->value());
            }
        }

        $this->options[] = $option;
    }

    public function configureValidation(QuestionValidation $validation): void
    {
        $this->validation = $validation;
    }

    public function configureVisibility(VisibilityRule $visibility): void
    {
        $this->visibility = $visibility;
    }
}
