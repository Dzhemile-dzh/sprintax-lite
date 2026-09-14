<?php

declare(strict_types=1);

namespace App\Domain\Submission;

use App\Domain\Questionnaire\QuestionVisibilityEvaluator;
use App\Domain\Submission\Entity\QuestionnaireSubmission;
use App\Domain\Submission\Exception\InvalidSubmission;
use App\Domain\Submission\ValueObject\AnswerValue;

final class SubmissionCompleteness
{
    public function __construct(
        private readonly QuestionVisibilityEvaluator $visibility,
        private readonly QuestionAnswerValidator $validator,
    ) {
    }

    public function assertComplete(QuestionnaireSubmission $submission): void
    {
        $answersByKey = $submission->answersByQuestionKey();

        foreach ($submission->questionnaire()->allQuestions() as $question) {
            if (!$this->visibility->isVisible($question, $submission->questionnaire(), $answersByKey)) {
                continue;
            }

            $this->validator->validate(
                $question,
                $answersByKey[$question->key()] ?? AnswerValue::text(''),
            );
        }
    }

    public function isComplete(QuestionnaireSubmission $submission): bool
    {
        try {
            $this->assertComplete($submission);
        } catch (InvalidSubmission) {
            return false;
        }

        return true;
    }
}
