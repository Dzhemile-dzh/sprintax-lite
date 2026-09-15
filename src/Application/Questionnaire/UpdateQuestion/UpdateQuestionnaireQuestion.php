<?php

declare(strict_types=1);

namespace App\Application\Questionnaire\UpdateQuestion;

use App\Application\Audit\RecordQuestionnaireRevision;
use App\Application\Questionnaire\QuestionFormMapping;
use App\Domain\Audit\ValueObject\RevisionAction;
use App\Domain\Questionnaire\Exception\InvalidQuestionnaire;
use App\Domain\Questionnaire\Repository\QuestionnaireRepositoryInterface;

final class UpdateQuestionnaireQuestion
{
    public function __construct(
        private readonly QuestionnaireRepositoryInterface $questionnaires,
        private readonly RecordQuestionnaireRevision $revisions,
    ) {
    }

    public function execute(
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
}
