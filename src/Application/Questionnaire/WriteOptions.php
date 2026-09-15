<?php

declare(strict_types=1);

namespace App\Application\Questionnaire;

use App\Application\Audit\RecordQuestionnaireRevision;
use App\Domain\Audit\ValueObject\RevisionAction;
use App\Domain\Questionnaire\Entity\QuestionOption;
use App\Domain\Questionnaire\Exception\InvalidQuestionnaire;
use App\Domain\Questionnaire\Repository\QuestionnaireRepositoryInterface;
use App\Domain\Submission\Repository\SubmissionRepositoryInterface;

final class WriteOptions
{
    public function __construct(
        private readonly QuestionnaireRepositoryInterface $questionnaires,
        private readonly SubmissionRepositoryInterface $submissions,
        private readonly RecordQuestionnaireRevision $revisions,
    ) {
    }

    public function add(
        string $questionnaireId,
        string $questionId,
        string $label,
        string $value,
    ): QuestionOption {
        $questionnaire = $this->questionnaires->get($questionnaireId);
        $question = $questionnaire->findQuestion($questionId);

        if ($question === null) {
            throw InvalidQuestionnaire::questionNotFound($questionId);
        }

        $option = QuestionOption::create(
            bin2hex(random_bytes(16)),
            $label,
            $value,
            $question->nextOptionPosition(),
        );
        $question->addOption($option);
        $this->questionnaires->save($questionnaire);
        $this->revisions->execute(
            $questionnaire,
            RevisionAction::OptionAdded,
            sprintf('Added option "%s" to question "%s"', $option->value(), $question->key()),
        );

        return $option;
    }

    public function update(
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

    public function remove(string $questionnaireId, string $questionId, string $optionId): void
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
