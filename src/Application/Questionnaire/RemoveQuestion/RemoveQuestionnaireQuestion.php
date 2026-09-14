<?php

declare(strict_types=1);

namespace App\Application\Questionnaire\RemoveQuestion;

use App\Domain\Questionnaire\Exception\InvalidQuestionnaire;
use App\Domain\Questionnaire\Repository\QuestionnaireRepositoryInterface;
use App\Domain\Submission\Repository\SubmissionRepositoryInterface;

final class RemoveQuestionnaireQuestion
{
    public function __construct(
        private readonly QuestionnaireRepositoryInterface $questionnaires,
        private readonly SubmissionRepositoryInterface $submissions,
    ) {
    }

    public function execute(string $questionnaireId, string $questionId): void
    {
        if ($this->submissions->existsForQuestionnaire($questionnaireId)) {
            throw InvalidQuestionnaire::structureLocked();
        }

        $questionnaire = $this->questionnaires->get($questionnaireId);
        $questionnaire->removeQuestion($questionId);
        $this->questionnaires->save($questionnaire);
    }
}
