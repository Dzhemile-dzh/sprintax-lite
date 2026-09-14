<?php

declare(strict_types=1);

namespace App\Domain\Submission\Entity;

use App\Domain\Questionnaire\Entity\Question;
use App\Domain\Questionnaire\Entity\Questionnaire;
use App\Domain\Submission\Exception\InvalidSubmission;
use App\Domain\Submission\ValueObject\AnswerValue;
use App\Domain\Submission\ValueObject\SubmissionStatus;
use App\Domain\User\Entity\User;
use DateTimeImmutable;

final class QuestionnaireSubmission
{
    /** @var array<string, Answer> */
    private array $answers = [];

    private function __construct(
        private string $id,
        private Questionnaire $questionnaire,
        private User $user,
        private SubmissionStatus $status,
        private string $currentStepId,
        private DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
        private ?DateTimeImmutable $finalizedAt,
    ) {
    }

    public static function start(
        string $id,
        Questionnaire $questionnaire,
        User $user,
        DateTimeImmutable $startedAt,
    ): self {
        $firstStep = $questionnaire->firstStep();

        if ($firstStep === null) {
            throw InvalidSubmission::questionnaireHasNoSteps();
        }

        return new self(
            $id,
            $questionnaire,
            $user,
            SubmissionStatus::InProgress,
            $firstStep->id(),
            $startedAt,
            $startedAt,
            null,
        );
    }

    public function id(): string
    {
        return $this->id;
    }

    public function questionnaire(): Questionnaire
    {
        return $this->questionnaire;
    }

    public function user(): User
    {
        return $this->user;
    }

    public function status(): SubmissionStatus
    {
        return $this->status;
    }

    public function currentStepId(): string
    {
        return $this->currentStepId;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function finalizedAt(): ?DateTimeImmutable
    {
        return $this->finalizedAt;
    }

    /**
     * @return list<Answer>
     */
    public function answers(): array
    {
        return array_values($this->answers);
    }

    public function answerFor(string $questionId): ?Answer
    {
        return $this->answers[$questionId] ?? null;
    }

    public function recordAnswer(Question $question, AnswerValue $value, DateTimeImmutable $recordedAt): void
    {
        if (!$this->questionnaire->hasQuestion($question->id())) {
            throw InvalidSubmission::questionNotInQuestionnaire();
        }

        $existing = $this->answers[$question->id()] ?? null;

        if ($existing !== null) {
            $existing->replaceValue($value);
        } else {
            $this->answers[$question->id()] = new Answer($question, $value);
        }

        $this->updatedAt = $recordedAt;
    }

    public function moveToStep(string $stepId, DateTimeImmutable $movedAt): void
    {
        if (!$this->questionnaire->hasStep($stepId)) {
            throw InvalidSubmission::unknownStep($stepId);
        }

        $this->currentStepId = $stepId;
        $this->updatedAt = $movedAt;
    }

    public function finalize(DateTimeImmutable $finalizedAt): void
    {
        if ($this->status !== SubmissionStatus::InProgress) {
            throw InvalidSubmission::cannotFinalize($this->status);
        }

        $this->status = SubmissionStatus::Finalized;
        $this->finalizedAt = $finalizedAt;
        $this->updatedAt = $finalizedAt;
    }

    public function markPdfReady(DateTimeImmutable $readyAt): void
    {
        if ($this->status !== SubmissionStatus::Finalized) {
            throw InvalidSubmission::cannotMarkPdfReady($this->status);
        }

        $this->status = SubmissionStatus::PdfReady;
        $this->updatedAt = $readyAt;
    }
}
