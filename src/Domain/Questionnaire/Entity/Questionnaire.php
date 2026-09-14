<?php

declare(strict_types=1);

namespace App\Domain\Questionnaire\Entity;

use App\Domain\Questionnaire\Exception\InvalidQuestionnaire;
use App\Domain\Questionnaire\ValueObject\QuestionType;
use App\Domain\Questionnaire\ValueObject\QuestionValidation;
use App\Domain\Questionnaire\ValueObject\VisibilityRule;

final class Questionnaire
{
    /** @var list<QuestionnaireStep> */
    private array $steps = [];

    /** @var list<QuestionMapping> */
    private array $mappings = [];

    private function __construct(
        private string $id,
        private string $name,
        private ?string $description,
    ) {
        if (trim($this->id) === '') {
            throw InvalidQuestionnaire::blank('id');
        }

        if (trim($this->name) === '') {
            throw InvalidQuestionnaire::blank('name');
        }

        if ($this->description !== null && trim($this->description) === '') {
            $this->description = null;
        }
    }

    public static function create(string $id, string $name, ?string $description = null): self
    {
        return new self($id, $name, $description);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function rename(string $name): void
    {
        if (trim($name) === '') {
            throw InvalidQuestionnaire::blank('name');
        }

        $this->name = $name;
    }

    public function changeDescription(?string $description): void
    {
        $this->description = $description !== null && trim($description) === '' ? null : $description;
    }

    /**
     * @return list<QuestionnaireStep>
     */
    public function steps(): array
    {
        $steps = $this->steps;
        usort(
            $steps,
            static fn (QuestionnaireStep $left, QuestionnaireStep $right): int => $left->position() <=> $right->position(),
        );

        return $steps;
    }

    public function addStep(string $id, string $title): QuestionnaireStep
    {
        $step = QuestionnaireStep::create($id, $title, count($this->steps) + 1);
        $this->steps[] = $step;

        return $step;
    }

    public function addQuestion(
        string $stepId,
        string $questionId,
        string $key,
        string $label,
        QuestionType $type,
        ?string $helpText = null,
        ?QuestionValidation $validation = null,
        ?VisibilityRule $visibility = null,
    ): Question {
        if ($this->findQuestionByKey($key) !== null) {
            throw InvalidQuestionnaire::duplicateQuestionKey($key);
        }

        $step = $this->step($stepId);
        $question = Question::create(
            $questionId,
            $key,
            $label,
            $type,
            $step->nextQuestionPosition(),
            $helpText,
            $validation,
            $visibility,
        );
        $step->addQuestion($question);

        return $question;
    }

    public function addMapping(QuestionMapping $mapping): void
    {
        if ($mapping->source()->isQuestion() && !$this->hasQuestion($mapping->source()->reference)) {
            throw InvalidQuestionnaire::questionNotFound($mapping->source()->reference);
        }

        $this->mappings[] = $mapping;
    }

    /**
     * @return list<QuestionMapping>
     */
    public function mappings(): array
    {
        return $this->mappings;
    }

    public function firstStep(): ?QuestionnaireStep
    {
        $steps = $this->steps();

        return $steps[0] ?? null;
    }

    public function hasQuestion(string $questionId): bool
    {
        foreach ($this->steps as $step) {
            if ($step->hasQuestion($questionId)) {
                return true;
            }
        }

        return false;
    }

    public function findQuestionByKey(string $key): ?Question
    {
        foreach ($this->allQuestions() as $question) {
            if ($question->key() === $key) {
                return $question;
            }
        }

        return null;
    }

    public function findQuestion(string $questionId): ?Question
    {
        foreach ($this->allQuestions() as $question) {
            if ($question->id() === $questionId) {
                return $question;
            }
        }

        return null;
    }

    /**
     * @return list<Question>
     */
    public function allQuestions(): array
    {
        $questions = [];

        foreach ($this->steps() as $step) {
            foreach ($step->questions() as $question) {
                $questions[] = $question;
            }
        }

        return $questions;
    }

    public function hasStep(string $stepId): bool
    {
        foreach ($this->steps as $step) {
            if ($step->id() === $stepId) {
                return true;
            }
        }

        return false;
    }

    private function step(string $stepId): QuestionnaireStep
    {
        foreach ($this->steps as $step) {
            if ($step->id() === $stepId) {
                return $step;
            }
        }

        throw InvalidQuestionnaire::stepNotFound($stepId);
    }
}
