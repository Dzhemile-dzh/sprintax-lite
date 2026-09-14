<?php

declare(strict_types=1);

namespace App\Application\Submission\NextStep;

use App\Domain\Submission\Entity\QuestionnaireSubmission;

final class DetermineNextStep
{
    public function execute(QuestionnaireSubmission $submission, string $stepId): ?string
    {
        return $submission->questionnaire()->nextStepAfter($stepId)?->id();
    }
}
