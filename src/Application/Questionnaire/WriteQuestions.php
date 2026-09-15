<?php

declare(strict_types=1);

namespace App\Application\Questionnaire;

use App\Application\Audit\RecordQuestionnaireRevision;
use App\Domain\Audit\ValueObject\RevisionAction;
use App\Domain\Questionnaire\Entity\Question;
use App\Domain\Questionnaire\Exception\InvalidQuestionnaire;
use App\Domain\Questionnaire\Repository\QuestionnaireRepositoryInterface;
use App\Domain\Questionnaire\ValueObject\QuestionType;
use App\Domain\Submission\Repository\SubmissionRepositoryInterface;

final class WriteQuestions
{
    public function __construct(
        private readonly QuestionnaireRepositoryInterface $questionnaires,
        private readonly SubmissionRepositoryInterface $submissions,
        private readonly RecordQuestionnaireRevision $revisions,
    ) {
    }

    public function add(
        string $questionnaireId,
        string $stepId,
        string $key,
        string $label,
        QuestionType $type,
        ?string $helpText,
        bool $required,
        mixed $min,
        mixed $max,
        mixed $regex,
        mixed $visibilityQuestionKey,
        mixed $visibilityOperator,
        mixed $visibilityExpectedValue,
    ): Question {
        $questionnaire = $this->questionnaires->get($questionnaireId);
        $question = $questionnaire->addQuestion(
            $stepId,
            bin2hex(random_bytes(16)),
            $key,
            $label,
            $type,
            $helpText,
            QuestionFormMapping::validation($required, $min, $max, $regex),
            QuestionFormMapping::visibility($visibilityQuestionKey, $visibilityOperator, $visibilityExpectedValue),
        );
        $this->questionnaires->save($questionnaire);
        $this->revisions->execute(
            $questionnaire,
            RevisionAction::QuestionAdded,
            sprintf('Added %s question "%s" to step "%s"', $type->value, $key, $question->step()->title()),
        );

        return $question;
    }

    public function update(
        string $questionnaireId,
        string $questionId,
        string $label,
        ?string $helpText,
        bool $required,
        mixed $min,
        mixed $max,
        mixed $regex,
        mixed $visibilityQuestionKey,
        mixed $visibilityOperator,
        mixed $visibilityExpectedValue,
    ): void {
        $questionnaire = $this->questionnaires->get($questionnaireId);
        $question = $questionnaire->findQuestion($questionId);

        if ($question === null) {
            throw InvalidQuestionnaire::questionNotFound($questionId);
        }

        $questionnaire->updateQuestion(
            $questionId,
            $label,
            $helpText,
            QuestionFormMapping::validation($required, $min, $max, $regex),
            QuestionFormMapping::visibility($visibilityQuestionKey, $visibilityOperator, $visibilityExpectedValue),
        );
        $this->questionnaires->save($questionnaire);
        $this->revisions->execute(
            $questionnaire,
            RevisionAction::QuestionUpdated,
            sprintf('Updated question "%s"', $question->key()),
        );
    }

    public function remove(string $questionnaireId, string $questionId): void
    {
        if ($this->submissions->existsForQuestionnaire($questionnaireId)) {
            throw InvalidQuestionnaire::structureLocked();
        }

        $questionnaire = $this->questionnaires->get($questionnaireId);
        $removed = $questionnaire->findQuestion($questionId);
        $questionnaire->removeQuestion($questionId);
        $this->questionnaires->save($questionnaire);
        $this->revisions->execute(
            $questionnaire,
            RevisionAction::QuestionRemoved,
            sprintf('Removed question "%s"', $removed?->key() ?? $questionId),
        );
    }
}
