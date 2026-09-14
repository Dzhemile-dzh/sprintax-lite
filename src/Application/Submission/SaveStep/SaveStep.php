<?php

declare(strict_types=1);

namespace App\Application\Submission\SaveStep;

use App\Application\Submission\NextStep\DetermineNextStep;
use App\Domain\Questionnaire\Entity\Question;
use App\Domain\Questionnaire\QuestionVisibilityEvaluator;
use App\Domain\Questionnaire\ValueObject\QuestionType;
use App\Domain\Submission\Entity\QuestionnaireSubmission;
use App\Domain\Submission\Exception\InvalidSubmission;
use App\Domain\Submission\QuestionAnswerValidator;
use App\Domain\Submission\Repository\SubmissionRepositoryInterface;
use App\Domain\Submission\ValueObject\AnswerValue;
use App\Domain\Submission\ValueObject\SubmissionStatus;
use DateTimeImmutable;
use DateTimeInterface;

final class SaveStep
{
    public const NEXT_REVIEW = 'review';

    public function __construct(
        private readonly SubmissionRepositoryInterface $submissions,
        private readonly QuestionVisibilityEvaluator $visibility,
        private readonly QuestionAnswerValidator $validator,
        private readonly DetermineNextStep $determineNextStep,
    ) {
    }

    /**
     * @param array<string, mixed> $submitted
     */
    public function execute(string $submissionId, string $stepId, array $submitted, bool $advance): string
    {
        $submission = $this->submissions->get($submissionId);

        if ($submission->status() !== SubmissionStatus::InProgress) {
            throw InvalidSubmission::cannotEdit($submission->status());
        }

        $step = $submission->questionnaire()->findStep($stepId);

        if ($step === null) {
            throw InvalidSubmission::unknownStep($stepId);
        }

        if ($step->position() > $submission->currentStep()->position()) {
            throw InvalidSubmission::cannotSkipAhead();
        }

        $answersByKey = $submission->answersByQuestionKey();

        foreach ($step->questions() as $question) {
            if (array_key_exists($question->key(), $submitted)) {
                $answersByKey[$question->key()] = $this->toAnswerValue($question, $submitted[$question->key()]);
            }
        }

        $visible = $this->visibility->visibleQuestionsOnStep(
            $step,
            $submission->questionnaire(),
            $answersByKey,
        );
        $now = new DateTimeImmutable();
        $waitForNewlyVisible = false;

        foreach ($visible as $question) {
            if (!array_key_exists($question->key(), $submitted) && !$question->visibility()->isAlwaysVisible()) {
                $waitForNewlyVisible = true;
                continue;
            }

            $value = $this->toAnswerValue($question, $submitted[$question->key()] ?? null);
            $this->validator->validate($question, $value);
            $submission->recordAnswer($question, $value, $now);
        }

        $this->discardHiddenAnswers($submission, $now);

        if ($waitForNewlyVisible || !$advance) {
            $this->submissions->save($submission);

            return $stepId;
        }

        $nextStepId = $this->determineNextStep->execute($submission, $stepId);

        if ($nextStepId === null) {
            $this->submissions->save($submission);

            return self::NEXT_REVIEW;
        }

        $nextStep = $submission->questionnaire()->findStep($nextStepId);

        if ($nextStep !== null && $nextStep->position() > $submission->currentStep()->position()) {
            $submission->moveToStep($nextStepId, $now);
        }

        $this->submissions->save($submission);

        return $nextStepId;
    }

    private function toAnswerValue(Question $question, mixed $raw): AnswerValue
    {
        if ($question->type() === QuestionType::MultiChoice) {
            $choices = [];

            if (is_array($raw)) {
                foreach ($raw as $choice) {
                    if (is_string($choice) || is_int($choice) || is_float($choice)) {
                        $choices[] = (string) $choice;
                    }
                }
            }

            return AnswerValue::choices($choices);
        }

        if ($raw instanceof DateTimeInterface) {
            return AnswerValue::text($raw->format('Y-m-d'));
        }

        if ($raw === null) {
            return AnswerValue::text('');
        }

        if (is_bool($raw)) {
            return AnswerValue::text($raw ? 'yes' : 'no');
        }

        if (is_int($raw) || is_float($raw) || is_string($raw)) {
            return AnswerValue::text(trim((string) $raw));
        }

        return AnswerValue::text('');
    }

    private function discardHiddenAnswers(QuestionnaireSubmission $submission, DateTimeImmutable $discardedAt): void
    {
        $answersByKey = $submission->answersByQuestionKey();
        $visibleIds = [];

        foreach ($submission->questionnaire()->allQuestions() as $question) {
            if ($this->visibility->isVisible($question, $submission->questionnaire(), $answersByKey)) {
                $visibleIds[] = $question->id();
            }
        }

        $submission->discardAnswersNotIn($visibleIds, $discardedAt);
    }
}
