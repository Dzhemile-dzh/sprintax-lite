<?php

declare(strict_types=1);

namespace App\Application\Submission\GetWizardStep;

use App\Domain\Questionnaire\QuestionVisibilityEvaluator;
use App\Domain\Submission\Exception\InvalidSubmission;
use App\Domain\Submission\Repository\SubmissionRepositoryInterface;
use App\Domain\Submission\ValueObject\SubmissionStatus;

final class GetWizardStep
{
    public function __construct(
        private readonly SubmissionRepositoryInterface $submissions,
        private readonly QuestionVisibilityEvaluator $visibility,
    ) {
    }

    public function execute(string $submissionId, string $stepId): WizardStepView
    {
        $submission = $this->submissions->get($submissionId);

        if ($submission->status() !== SubmissionStatus::InProgress) {
            return WizardStepView::sendToReview($submission);
        }

        $step = $submission->questionnaire()->findStep($stepId);

        if ($step === null) {
            throw InvalidSubmission::unknownStep($stepId);
        }

        if ($step->position() > $submission->currentStep()->position()) {
            throw InvalidSubmission::cannotSkipAhead();
        }

        return WizardStepView::forEditing(
            $submission,
            $step,
            $this->visibility->visibleQuestionsOnStep(
                $step,
                $submission->questionnaire(),
                $submission->answersByQuestionKey(),
            ),
            $submission->questionnaire()->previousStepBefore($stepId)?->id(),
        );
    }
}
