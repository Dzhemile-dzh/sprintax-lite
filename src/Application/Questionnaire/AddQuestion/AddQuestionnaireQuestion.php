<?php

declare(strict_types=1);

namespace App\Application\Questionnaire\AddQuestion;

use App\Domain\Questionnaire\Entity\Question;
use App\Domain\Questionnaire\Repository\QuestionnaireRepositoryInterface;
use App\Domain\Questionnaire\ValueObject\QuestionType;
use App\Domain\Questionnaire\ValueObject\QuestionValidation;
use App\Domain\Questionnaire\ValueObject\VisibilityCondition;
use App\Domain\Questionnaire\ValueObject\VisibilityOperator;
use App\Domain\Questionnaire\ValueObject\VisibilityRule;

final class AddQuestionnaireQuestion
{
    public function __construct(
        private readonly QuestionnaireRepositoryInterface $questionnaires,
    ) {
    }

    public function execute(
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
            $this->validationFromForm($required, $min, $max, $regex),
            $this->visibilityFromForm($visibilityQuestionKey, $visibilityOperator, $visibilityExpectedValue),
        );
        $this->questionnaires->save($questionnaire);

        return $question;
    }

    private function validationFromForm(bool $required, mixed $min, mixed $max, mixed $regex): QuestionValidation
    {
        $pattern = is_string($regex) && trim($regex) !== '' ? $regex : null;

        return new QuestionValidation(
            required: $required,
            min: is_int($min) ? $min : null,
            max: is_int($max) ? $max : null,
            regex: $pattern,
        );
    }

    private function visibilityFromForm(mixed $questionKey, mixed $operator, mixed $expectedValue): VisibilityRule
    {
        if (
            !is_string($questionKey)
            || trim($questionKey) === ''
            || !$operator instanceof VisibilityOperator
        ) {
            return VisibilityRule::alwaysVisible();
        }

        $value = is_string($expectedValue) ? $expectedValue : '';

        return new VisibilityRule([
            new VisibilityCondition($questionKey, $operator, $value),
        ]);
    }
}
