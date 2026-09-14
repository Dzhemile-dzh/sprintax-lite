<?php

declare(strict_types=1);

namespace App\Domain\Questionnaire\Entity;

use App\Domain\Questionnaire\Exception\InvalidQuestionnaire;

final class QuestionnaireStep
{
    /** @var list<Question> */
    private array $questions = [];

    private function __construct(
        private string $id,
        private string $title,
        private int $position,
    ) {
        if (trim($this->id) === '') {
            throw InvalidQuestionnaire::blank('step id');
        }

        if (trim($this->title) === '') {
            throw InvalidQuestionnaire::blank('step title');
        }

        if ($this->position < 1) {
            throw InvalidQuestionnaire::blank('step position');
        }
    }

    public static function create(string $id, string $title, int $position): self
    {
        return new self($id, $title, $position);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function position(): int
    {
        return $this->position;
    }

    /**
     * @return list<Question>
     */
    public function questions(): array
    {
        $questions = $this->questions;
        usort(
            $questions,
            static fn (Question $left, Question $right): int => $left->position() <=> $right->position(),
        );

        return $questions;
    }

    public function addQuestion(Question $question): void
    {
        $this->questions[] = $question;
    }

    public function nextQuestionPosition(): int
    {
        return count($this->questions) + 1;
    }

    public function hasQuestion(string $questionId): bool
    {
        foreach ($this->questions as $question) {
            if ($question->id() === $questionId) {
                return true;
            }
        }

        return false;
    }
}
