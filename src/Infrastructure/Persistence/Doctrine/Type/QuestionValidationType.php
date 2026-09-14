<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\Questionnaire\ValueObject\QuestionValidation;
use InvalidArgumentException;

/**
 * @extends JsonValueObjectType<QuestionValidation>
 */
final class QuestionValidationType extends JsonValueObjectType
{
    public const NAME = 'question_validation';

    protected function valueClass(): string
    {
        return QuestionValidation::class;
    }

    protected function fromArray(array $data): QuestionValidation
    {
        if (!array_key_exists('required', $data) || !is_bool($data['required'])) {
            throw new InvalidArgumentException('Invalid question_validation JSON.');
        }

        $min = $data['min'] ?? null;
        $max = $data['max'] ?? null;
        $regex = $data['regex'] ?? null;

        if ($min !== null && !is_int($min)) {
            throw new InvalidArgumentException('Invalid question_validation min.');
        }

        if ($max !== null && !is_int($max)) {
            throw new InvalidArgumentException('Invalid question_validation max.');
        }

        if ($regex !== null && !is_string($regex)) {
            throw new InvalidArgumentException('Invalid question_validation regex.');
        }

        return new QuestionValidation(
            required: $data['required'],
            min: $min,
            max: $max,
            regex: $regex,
        );
    }

    protected function toArray(object $value): array
    {
        assert($value instanceof QuestionValidation);

        return [
            'required' => $value->required,
            'min' => $value->min,
            'max' => $value->max,
            'regex' => $value->regex,
        ];
    }
}
