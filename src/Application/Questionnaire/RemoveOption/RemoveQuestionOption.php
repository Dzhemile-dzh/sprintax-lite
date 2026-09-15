<?php

declare(strict_types=1);

namespace App\Application\Questionnaire\RemoveOption;

use App\Application\Audit\RecordQuestionnaireRevision;
use App\Domain\Audit\ValueObject\RevisionAction;
use App\Domain\Questionnaire\Exception\InvalidQuestionnaire;
use App\Domain\Questionnaire\Repository\QuestionnaireRepositoryInterface;
use App\Domain\Submission\Repository\SubmissionRepositoryInterface;

final class RemoveQuestionOption
{
    public function __construct(
        private readonly QuestionnaireRepositoryInterface $questionnaires,
        private readonly SubmissionRepositoryInterface $submissions,
        private readonly RecordQuestionnaireRevision $revisions,
    ) {
    }

    public function execute(string $questionnaireId, string $questionId, string $optionId): void
    {
        if ($this->submissions->existsForQuestionnaire($questionnaireId)) {
            throw InvalidQuestionnaire::structureLocked();
        }

        $questionnaire = $this->questionnaires->get($questionnaireId);
        $question = $questionnaire->findQuestion($questionId);
        $questionnaire->removeOption($questionId, $optionId);
        $this->questionnaires->save($questionnaire);
        $this->revisions->execute(
            $questionnaire,
            RevisionAction::OptionRemoved,
            sprintf('Removed an option from question "%s"', $question?->key() ?? $questionId),
        );
    }
}
