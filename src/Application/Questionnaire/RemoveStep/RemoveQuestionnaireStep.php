<?php

declare(strict_types=1);

namespace App\Application\Questionnaire\RemoveStep;

use App\Domain\Questionnaire\Exception\InvalidQuestionnaire;
use App\Domain\Questionnaire\Repository\QuestionnaireRepositoryInterface;
use App\Domain\Submission\Repository\SubmissionRepositoryInterface;

final class RemoveQuestionnaireStep
{
    public function __construct(
        private readonly QuestionnaireRepositoryInterface $questionnaires,
        private readonly SubmissionRepositoryInterface $submissions,
    ) {
    }

    public function execute(string $questionnaireId, string $stepId): void
    {
        if ($this->submissions->existsForQuestionnaire($questionnaireId)) {
            throw InvalidQuestionnaire::structureLocked();
        }

        $questionnaire = $this->questionnaires->get($questionnaireId);
        $questionnaire->removeStep($stepId);
        $this->questionnaires->save($questionnaire);
    }
}
