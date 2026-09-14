<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\Submission\ValueObject\AnswerValue;
use InvalidArgumentException;

/**
 * @extends JsonValueObjectType<AnswerValue>
 */
final class AnswerValueType extends JsonValueObjectType
{
    public const NAME = 'answer_value';

    protected function valueClass(): string
    {
        return AnswerValue::class;
    }

    protected function fromArray(array $data): AnswerValue
    {
        if (array_key_exists('choices', $data)) {
            if (!is_array($data['choices'])) {
                throw new InvalidArgumentException('Invalid answer_value choices JSON.');
            }

            $choices = [];

            foreach ($data['choices'] as $choice) {
                if (!is_string($choice)) {
                    throw new InvalidArgumentException('Invalid answer_value choice JSON.');
                }

                $choices[] = $choice;
            }

            return AnswerValue::choices($choices);
        }

        if (!isset($data['text']) || !is_string($data['text'])) {
            throw new InvalidArgumentException('Invalid answer_value text JSON.');
        }

        return AnswerValue::text($data['text']);
    }

    protected function toArray(object $value): array
    {
        assert($value instanceof AnswerValue);

        $raw = $value->raw();

        if (is_array($raw)) {
            return ['choices' => $raw];
        }

        return ['text' => $raw];
    }
}
