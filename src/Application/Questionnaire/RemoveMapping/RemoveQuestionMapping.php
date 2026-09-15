<?php

declare(strict_types=1);

namespace App\Application\Questionnaire\RemoveMapping;

use App\Application\Audit\RecordQuestionnaireRevision;
use App\Domain\Audit\ValueObject\RevisionAction;
use App\Domain\Questionnaire\Repository\QuestionnaireRepositoryInterface;

final class RemoveQuestionMapping
{
    public function __construct(
        private readonly QuestionnaireRepositoryInterface $questionnaires,
        private readonly RecordQuestionnaireRevision $revisions,
    ) {
    }

    public function execute(string $questionnaireId, string $mappingId): void
    {
        $questionnaire = $this->questionnaires->get($questionnaireId);
        $removed = $questionnaire->findMapping($mappingId);
        $questionnaire->removeMapping($mappingId);
        $this->questionnaires->save($questionnaire);
        $this->revisions->execute(
            $questionnaire,
            RevisionAction::MappingRemoved,
            sprintf('Removed the mapping for "%s"', $removed?->source()->reference ?? $mappingId),
        );
    }
}
