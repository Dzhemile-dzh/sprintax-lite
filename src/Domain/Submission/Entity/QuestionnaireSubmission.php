<?php

declare(strict_types=1);

namespace App\Domain\Submission\Entity;

use App\Domain\Questionnaire\Entity\Question;
use App\Domain\Questionnaire\Entity\Questionnaire;
use App\Domain\Questionnaire\Entity\QuestionnaireStep;
use App\Domain\Submission\Exception\InvalidSubmission;
use App\Domain\Submission\ValueObject\AnswerValue;
use App\Domain\Submission\ValueObject\SubmissionStatus;
use App\Domain\User\Entity\User;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'questionnaire_submission')]
#[ORM\UniqueConstraint(name: 'uniq_submission_user_questionnaire', columns: ['user_id', 'questionnaire_id'])]
#[ORM\Index(name: 'idx_submission_questionnaire', columns: ['questionnaire_id'])]
final class QuestionnaireSubmission
{
    #[ORM\Id]
    #[ORM\Column(length: 64)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Questionnaire::class)]
    #[ORM\JoinColumn(name: 'questionnaire_id', nullable: false)]
    private Questionnaire $questionnaire;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: false)]
    private User $user;

    #[ORM\Column(enumType: SubmissionStatus::class, length: 32)]
    private SubmissionStatus $status;

    #[ORM\ManyToOne(targetEntity: QuestionnaireStep::class)]
    #[ORM\JoinColumn(name: 'current_step_id', referencedColumnName: 'id', nullable: false)]
    private QuestionnaireStep $currentStep;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private DateTimeImmutable $updatedAt;

    #[ORM\Column(name: 'finalized_at', type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $finalizedAt;

    /**
     * @var Collection<int, Answer>
     */
    #[ORM\OneToMany(targetEntity: Answer::class, mappedBy: 'submission', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $answers;

    private function __construct(
        string $id,
        Questionnaire $questionnaire,
        User $user,
        SubmissionStatus $status,
        QuestionnaireStep $currentStep,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
        ?DateTimeImmutable $finalizedAt,
    ) {
        $this->id = $id;
        $this->questionnaire = $questionnaire;
        $this->user = $user;
        $this->status = $status;
        $this->currentStep = $currentStep;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
        $this->finalizedAt = $finalizedAt;
        $this->answers = new ArrayCollection();
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
            $firstStep,
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
        return $this->currentStep->id();
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
        /** @var list<Answer> $answers */
        $answers = $this->answers->toArray();

        return $answers;
    }

    public function answerFor(string $questionId): ?Answer
    {
        foreach ($this->answers as $answer) {
            if ($answer->question()->id() === $questionId) {
                return $answer;
            }
        }

        return null;
    }

    public function recordAnswer(Question $question, AnswerValue $value, DateTimeImmutable $recordedAt): void
    {
        if (!$this->questionnaire->hasQuestion($question->id())) {
            throw InvalidSubmission::questionNotInQuestionnaire();
        }

        $existing = $this->answerFor($question->id());

        if ($existing !== null) {
            $existing->replaceValue($value);
        } else {
            $this->answers->add(new Answer($this, $question, $value));
        }

        $this->updatedAt = $recordedAt;
    }

    public function moveToStep(string $stepId, DateTimeImmutable $movedAt): void
    {
        foreach ($this->questionnaire->steps() as $step) {
            if ($step->id() === $stepId) {
                $this->currentStep = $step;
                $this->updatedAt = $movedAt;

                return;
            }
        }

        throw InvalidSubmission::unknownStep($stepId);
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
