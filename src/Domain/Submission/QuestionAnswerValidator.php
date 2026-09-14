<?php

declare(strict_types=1);

namespace App\Domain\Submission;

use App\Domain\Questionnaire\Entity\Question;
use App\Domain\Questionnaire\ValueObject\QuestionType;
use App\Domain\Submission\Exception\InvalidSubmission;
use App\Domain\Submission\ValueObject\AnswerValue;
use DateTimeImmutable;

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

        $this->assertScalar($question, $raw, $rules->min, $rules->max, $rules->regex);
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

    private function assertScalar(
        Question $question,
        string $raw,
        ?int $min,
        ?int $max,
        ?string $regex,
    ): void {
        if ($question->type() === QuestionType::YesNo && !in_array($raw, ['yes', 'no'], true)) {
            throw InvalidSubmission::invalidAnswer($question->key(), 'must be yes or no');
        }

        if ($question->type() === QuestionType::Date) {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $raw);

            if ($date === false || $date->format('Y-m-d') !== $raw) {
                throw InvalidSubmission::invalidAnswer($question->key(), 'must be a valid date');
            }
        }

        if ($question->type() === QuestionType::Number) {
            if (!is_numeric($raw)) {
                throw InvalidSubmission::invalidAnswer($question->key(), 'must be a number');
            }

            $number = (float) $raw;

            if ($min !== null && $number < $min) {
                throw InvalidSubmission::invalidAnswer($question->key(), 'is below the minimum');
            }

            if ($max !== null && $number > $max) {
                throw InvalidSubmission::invalidAnswer($question->key(), 'is above the maximum');
            }

            return;
        }

        if ($question->type() === QuestionType::SingleChoice) {
            $this->assertAllowedChoices($question, [$raw]);
        }

        $length = strlen($raw);

        if ($min !== null && $length < $min) {
            throw InvalidSubmission::invalidAnswer($question->key(), 'is too short');
        }

        if ($max !== null && $length > $max) {
            throw InvalidSubmission::invalidAnswer($question->key(), 'is too long');
        }

        if ($regex !== null) {
            $matched = @preg_match('/'.$regex.'/', $raw);

            if ($matched !== 1) {
                throw InvalidSubmission::invalidAnswer($question->key(), 'does not match the expected format');
            }
        }
    }
}
