<?php

declare(strict_types=1);

namespace App\Application\Submission\Review;

use App\Domain\Questionnaire\Entity\Question;
use App\Domain\Questionnaire\Entity\QuestionnaireStep;
use App\Domain\Questionnaire\QuestionVisibilityEvaluator;
use App\Domain\Submission\Entity\QuestionnaireSubmission;
use App\Domain\Submission\Repository\SubmissionRepositoryInterface;
use App\Domain\Submission\ValueObject\AnswerValue;

final class ReviewSubmission
{
    public function __construct(
        private readonly SubmissionRepositoryInterface $submissions,
        private readonly QuestionVisibilityEvaluator $visibility,
    ) {
    }

    public function execute(string $submissionId): QuestionnaireSubmission
    {
        return $this->submissions->get($submissionId);
    }

    /**
     * @return list<array{step: QuestionnaireStep, questions: list<array{question: Question, answer: ?AnswerValue}>}>
     */
    public function visibleAnswersByStep(QuestionnaireSubmission $submission): array
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
