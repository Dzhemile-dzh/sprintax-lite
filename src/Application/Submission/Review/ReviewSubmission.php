<?php

declare(strict_types=1);

namespace App\Application\Submission\Review;

use App\Domain\Questionnaire\Entity\Question;
use App\Domain\Questionnaire\Entity\QuestionnaireStep;
use App\Domain\Questionnaire\QuestionVisibilityEvaluator;
use App\Domain\Submission\Entity\QuestionnaireSubmission;
use App\Domain\Submission\Repository\SubmissionRepositoryInterface;
use App\Domain\Submission\SubmissionCompleteness;
use App\Domain\Submission\ValueObject\AnswerValue;
use App\Domain\Submission\ValueObject\SubmissionStatus;

final class ReviewSubmission
{
    public function __construct(
        private readonly SubmissionRepositoryInterface $submissions,
        private readonly QuestionVisibilityEvaluator $visibility,
        private readonly SubmissionCompleteness $completeness,
    ) {
    }

    public function execute(string $submissionId): ReviewView
    {
        $submission = $this->submissions->get($submissionId);
        $inProgress = $submission->status() === SubmissionStatus::InProgress;
        $complete = $inProgress && $this->completeness->isComplete($submission);

        return new ReviewView(
            $submission,
            $this->visibleAnswersByStep($submission),
            $inProgress && $complete,
            $inProgress && !$complete ? $submission->currentStep()->id() : null,
        );
    }

    /**
     * @return list<array{step: QuestionnaireStep, questions: list<array{question: Question, answer: ?AnswerValue}>}>
     */
    private function visibleAnswersByStep(QuestionnaireSubmission $submission): array
    {
        $answersByKey = $submission->answersByQuestionKey();
        $steps = [];

        foreach ($submission->questionnaire()->steps() as $step) {
            $questions = [];

            foreach ($this->visibility->visibleQuestionsOnStep(
                $step,
                $submission->questionnaire(),
                $answersByKey,
            ) as $question) {
                $questions[] = [
                    'question' => $question,
                    'answer' => $answersByKey[$question->key()] ?? null,
                ];
            }

            if ($questions === []) {
                continue;
            }

            $steps[] = [
                'step' => $step,
                'questions' => $questions,
            ];
        }

        return $steps;
    }
}
