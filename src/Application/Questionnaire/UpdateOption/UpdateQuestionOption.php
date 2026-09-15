<?php

declare(strict_types=1);

namespace App\Application\Questionnaire\UpdateOption;

use App\Application\Audit\RecordQuestionnaireRevision;
use App\Domain\Audit\ValueObject\RevisionAction;
use App\Domain\Questionnaire\Repository\QuestionnaireRepositoryInterface;

final class UpdateQuestionOption
{
    public function __construct(
        private readonly QuestionnaireRepositoryInterface $questionnaires,
        private readonly RecordQuestionnaireRevision $revisions,
    ) {
    }

    public function execute(
        string $questionnaireId,
        string $questionId,
        string $optionId,
        string $label,
        string $value,
    ): void {
        $questionnaire = $this->questionnaires->get($questionnaireId);
        $questionnaire->updateOption($questionId, $optionId, $label, $value);
        $this->questionnaires->save($questionnaire);
        $this->revisions->execute(
            $questionnaire,
            RevisionAction::OptionUpdated,
            sprintf('Updated option to "%s" (%s)', $label, $value),
        );
    }
}
