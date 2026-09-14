<?php

declare(strict_types=1);

namespace App\Application\Questionnaire;

use App\Domain\Questionnaire\ValueObject\QuestionValidation;
use App\Domain\Questionnaire\ValueObject\VisibilityCondition;
use App\Domain\Questionnaire\ValueObject\VisibilityOperator;
use App\Domain\Questionnaire\ValueObject\VisibilityRule;

final class QuestionFormMapping
{
    public static function validation(bool $required, mixed $min, mixed $max, mixed $regex): QuestionValidation
    {
        $pattern = is_string($regex) && trim($regex) !== '' ? $regex : null;

        return new QuestionValidation(
            required: $required,
            min: is_int($min) ? $min : null,
            max: is_int($max) ? $max : null,
            regex: $pattern,
        );
    }

    public static function visibility(mixed $questionKey, mixed $operator, mixed $expectedValue): VisibilityRule
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
