<?php

declare(strict_types=1);

namespace App\Application\Questionnaire\UpdateStep;

use App\Application\Audit\RecordQuestionnaireRevision;
use App\Domain\Audit\ValueObject\RevisionAction;
use App\Domain\Questionnaire\Repository\QuestionnaireRepositoryInterface;

final class UpdateQuestionnaireStep
{
    public function __construct(
        private readonly QuestionnaireRepositoryInterface $questionnaires,
        private readonly RecordQuestionnaireRevision $revisions,
    ) {
    }

    public function execute(string $questionnaireId, string $stepId, string $title): void
    {
        $questionnaire = $this->questionnaires->get($questionnaireId);
        $questionnaire->renameStep($stepId, $title);
        $this->questionnaires->save($questionnaire);
        $this->revisions->execute(
            $questionnaire,
            RevisionAction::StepRenamed,
            sprintf('Renamed a step to "%s"', $title),
        );
    }
}
