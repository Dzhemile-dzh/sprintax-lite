<?php

declare(strict_types=1);

namespace App\Application\Questionnaire\AddStep;

use App\Application\Audit\RecordQuestionnaireRevision;
use App\Domain\Audit\ValueObject\RevisionAction;
use App\Domain\Questionnaire\Entity\QuestionnaireStep;
use App\Domain\Questionnaire\Repository\QuestionnaireRepositoryInterface;

final class AddQuestionnaireStep
{
    public function __construct(
        private readonly QuestionnaireRepositoryInterface $questionnaires,
        private readonly RecordQuestionnaireRevision $revisions,
    ) {
    }

    public function execute(string $questionnaireId, string $title): QuestionnaireStep
    {
        $questionnaire = $this->questionnaires->get($questionnaireId);
        $step = $questionnaire->addStep(bin2hex(random_bytes(16)), $title);
        $this->questionnaires->save($questionnaire);
        $this->revisions->execute(
            $questionnaire,
            RevisionAction::StepAdded,
            sprintf('Added step "%s" at position %d', $step->title(), $step->position()),
        );

        return $step;
    }
}
