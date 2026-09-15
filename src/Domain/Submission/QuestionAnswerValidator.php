<?php

declare(strict_types=1);

namespace App\Domain\Submission;

use App\Domain\Questionnaire\Entity\Question;
use App\Domain\Questionnaire\ValueObject\QuestionType;
use App\Domain\Questionnaire\ValueObject\QuestionValidation;
use App\Domain\Submission\Exception\InvalidSubmission;
use App\Domain\Submission\ValueObject\AnswerValue;
use App\Domain\Submission\ValueObject\StoredDate;

final class QuestionAnswerValidator
{
    public function validate(Question $question, AnswerValue $value): void
    {
        $rules = $question->validation();
        $raw = $value->raw();
        $missing = !$value->isProvided() || (is_array($raw) && $raw === []);

        if ($rules->required && $missing) {
            throw InvalidSubmission::requiredAnswer($question->key());
        }

        if ($missing) {
            return;
        }

        if (is_array($raw)) {
            $this->assertAllowedChoices($question, $raw);

            return;
        }

        $this->assertScalar($question, $raw, $rules);
    }

    /**
     * @param list<string> $choices
     */
    private function assertAllowedChoices(Question $question, array $choices): void
    {
        $allowed = [];

        foreach ($question->options() as $option) {
            $allowed[] = $option->value();
        }

        if ($allowed === []) {
            return;
        }

        foreach ($choices as $choice) {
            if (!in_array($choice, $allowed, true)) {
                throw InvalidSubmission::invalidAnswer($question->key(), 'value is not a valid option');
            }
        }
    }

    private function assertScalar(Question $question, string $raw, QuestionValidation $rules): void
    {
        if ($question->type() === QuestionType::YesNo && !in_array($raw, ['yes', 'no'], true)) {
            throw InvalidSubmission::invalidAnswer($question->key(), 'must be yes or no');
        }

        if ($question->type() === QuestionType::Date) {
            if (StoredDate::tryFrom($raw) === null) {
                throw InvalidSubmission::invalidAnswer($question->key(), 'must be a valid date');
            }
        }

        if ($question->type() === QuestionType::Number) {
            if (!is_numeric($raw)) {
                throw InvalidSubmission::invalidAnswer($question->key(), 'must be a number');
            }

            $number = (float) $raw;

            if ($rules->min !== null && $number < $rules->min) {
                throw InvalidSubmission::invalidAnswer($question->key(), 'is below the minimum');
            }

            if ($rules->max !== null && $number > $rules->max) {
                throw InvalidSubmission::invalidAnswer($question->key(), 'is above the maximum');
            }

            return;
        }

        if ($question->type() === QuestionType::SingleChoice) {
            $this->assertAllowedChoices($question, [$raw]);
        }

        $length = strlen($raw);

        if ($rules->min !== null && $length < $rules->min) {
            throw InvalidSubmission::invalidAnswer($question->key(), 'is too short');
        }

        if ($rules->max !== null && $length > $rules->max) {
            throw InvalidSubmission::invalidAnswer($question->key(), 'is too long');
        }

        if ($rules->regex !== null && !$rules->matchesPattern($raw)) {
            throw InvalidSubmission::invalidAnswer($question->key(), 'does not match the expected format');
        }
    }
}
