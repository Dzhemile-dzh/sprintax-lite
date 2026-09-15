<?php

declare(strict_types=1);

namespace App\Application\Questionnaire;

use App\Application\Audit\RecordQuestionnaireRevision;
use App\Domain\Audit\ValueObject\RevisionAction;
use App\Domain\Questionnaire\Entity\QuestionnaireStep;
use App\Domain\Questionnaire\Exception\InvalidQuestionnaire;
use App\Domain\Questionnaire\Repository\QuestionnaireRepositoryInterface;
use App\Domain\Submission\Repository\SubmissionRepositoryInterface;

final class WriteSteps
{
    public function __construct(
        private readonly QuestionnaireRepositoryInterface $questionnaires,
        private readonly SubmissionRepositoryInterface $submissions,
        private readonly RecordQuestionnaireRevision $revisions,
    ) {
    }

    public function add(string $questionnaireId, string $title): QuestionnaireStep
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

    public function update(string $questionnaireId, string $stepId, string $title): void
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

    public function remove(string $questionnaireId, string $stepId): void
    {
        if ($this->submissions->existsForQuestionnaire($questionnaireId)) {
            throw InvalidQuestionnaire::structureLocked();
        }

        $questionnaire = $this->questionnaires->get($questionnaireId);
        $removed = $questionnaire->findStep($stepId);
        $questionnaire->removeStep($stepId);
        $this->questionnaires->save($questionnaire);
        $this->revisions->execute(
            $questionnaire,
            RevisionAction::StepRemoved,
            sprintf('Removed step "%s"', $removed?->title() ?? $stepId),
        );
    }
}
