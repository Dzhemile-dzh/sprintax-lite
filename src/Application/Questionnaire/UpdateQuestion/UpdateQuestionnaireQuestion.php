<?php

declare(strict_types=1);

namespace App\Application\Questionnaire\UpdateQuestion;

use App\Application\Questionnaire\QuestionFormMapping;
use App\Domain\Questionnaire\Exception\InvalidQuestionnaire;
use App\Domain\Questionnaire\Repository\QuestionnaireRepositoryInterface;

final class UpdateQuestionnaireQuestion
{
    public function __construct(
        private readonly QuestionnaireRepositoryInterface $questionnaires,
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

        if ($questionnaire->findQuestion($questionId) === null) {
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
    }
}
