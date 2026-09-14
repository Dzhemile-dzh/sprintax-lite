<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\Questionnaire\ValueObject\VisibilityCondition;
use App\Domain\Questionnaire\ValueObject\VisibilityOperator;
use App\Domain\Questionnaire\ValueObject\VisibilityRule;
use InvalidArgumentException;

/**
 * @extends JsonValueObjectType<VisibilityRule>
 */
final class VisibilityRuleType extends JsonValueObjectType
{
    public const NAME = 'visibility_rule';

    protected function valueClass(): string
    {
        return VisibilityRule::class;
    }

    protected function fromArray(array $data): VisibilityRule
    {
        $conditions = [];

        if (!isset($data['conditions']) || !is_array($data['conditions'])) {
            throw new InvalidArgumentException('Invalid visibility_rule JSON.');
        }

        foreach ($data['conditions'] as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException('Invalid visibility condition JSON.');
            }

            $questionKey = $row['questionKey'] ?? null;
            $operator = $row['operator'] ?? null;
            $expectedValue = $row['expectedValue'] ?? null;

            if (!is_string($questionKey) || !is_string($operator) || !is_string($expectedValue)) {
                throw new InvalidArgumentException('Invalid visibility condition JSON.');
            }

            $conditions[] = new VisibilityCondition(
                $questionKey,
                VisibilityOperator::from($operator),
                $expectedValue,
            );
        }

        return new VisibilityRule($conditions);
    }

    protected function toArray(object $value): array
    {
        assert($value instanceof VisibilityRule);

        $conditions = [];

        foreach ($value->conditions as $condition) {
            $conditions[] = [
                'questionKey' => $condition->questionKey,
                'operator' => $condition->operator->value,
                'expectedValue' => $condition->expectedValue,
            ];
        }

        return ['conditions' => $conditions];
    }
}
