<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\Audit\DTO\ConditionSnapshot;
use App\Domain\Audit\DTO\MappingSnapshot;
use App\Domain\Audit\DTO\OptionSnapshot;
use App\Domain\Audit\DTO\QuestionSnapshot;
use App\Domain\Audit\DTO\StepSnapshot;
use App\Domain\Audit\DTO\StructureSnapshot;
use InvalidArgumentException;

/**
 * @extends JsonValueObjectType<StructureSnapshot>
 */
final class StructureSnapshotType extends JsonValueObjectType
{
    public const NAME = 'structure_snapshot';

    protected function valueClass(): string
    {
        return StructureSnapshot::class;
    }

    protected function fromArray(array $data): StructureSnapshot
    {
        $steps = [];

        foreach ($this->rows($data, 'steps') as $step) {
            $questions = [];

            foreach ($this->rows($step, 'questions') as $question) {
                $questions[] = $this->question($question);
            }

            $steps[] = new StepSnapshot(
                $this->string($step, 'title'),
                $this->int($step, 'position'),
                $questions,
            );
        }

        $mappings = [];

        foreach ($this->rows($data, 'mappings') as $mapping) {
            $mappings[] = new MappingSnapshot(
                $this->string($mapping, 'sourceType'),
                $this->string($mapping, 'sourceReference'),
                $this->int($mapping, 'page'),
                $this->float($mapping, 'xMm'),
                $this->float($mapping, 'yMm'),
                $this->nullableInt($mapping, 'fontSize'),
            );
        }

        return new StructureSnapshot(
            $this->string($data, 'name'),
            $this->string($data, 'formType'),
            $this->nullableString($data, 'description'),
            $steps,
            $mappings,
        );
    }

    protected function toArray(object $value): array
    {
        assert($value instanceof StructureSnapshot);

        $steps = [];

        foreach ($value->steps as $step) {
            $questions = [];

            foreach ($step->questions as $question) {
                $options = [];

                foreach ($question->options as $option) {
                    $options[] = [
                        'label' => $option->label,
                        'value' => $option->value,
                        'position' => $option->position,
                    ];
                }

                $conditions = [];

                foreach ($question->conditions as $condition) {
                    $conditions[] = [
                        'questionKey' => $condition->questionKey,
                        'operator' => $condition->operator,
                        'expectedValue' => $condition->expectedValue,
                    ];
                }

                $questions[] = [
                    'key' => $question->key,
                    'label' => $question->label,
                    'type' => $question->type,
                    'position' => $question->position,
                    'helpText' => $question->helpText,
                    'required' => $question->required,
                    'min' => $question->min,
                    'max' => $question->max,
                    'regex' => $question->regex,
                    'options' => $options,
                    'conditions' => $conditions,
                ];
            }

            $steps[] = [
                'title' => $step->title,
                'position' => $step->position,
                'questions' => $questions,
            ];
        }

        $mappings = [];

        foreach ($value->mappings as $mapping) {
            $mappings[] = [
                'sourceType' => $mapping->sourceType,
                'sourceReference' => $mapping->sourceReference,
                'page' => $mapping->page,
                'xMm' => $mapping->xMm,
                'yMm' => $mapping->yMm,
                'fontSize' => $mapping->fontSize,
            ];
        }

        return [
            'name' => $value->name,
            'formType' => $value->formType,
            'description' => $value->description,
            'steps' => $steps,
            'mappings' => $mappings,
        ];
    }

    /**
     * @param array<mixed> $data
     */
    private function question(array $data): QuestionSnapshot
    {
        $options = [];

        foreach ($this->rows($data, 'options') as $option) {
            $options[] = new OptionSnapshot(
                $this->string($option, 'label'),
                $this->string($option, 'value'),
                $this->int($option, 'position'),
            );
        }

        $conditions = [];

        foreach ($this->rows($data, 'conditions') as $condition) {
            $conditions[] = new ConditionSnapshot(
                $this->string($condition, 'questionKey'),
                $this->string($condition, 'operator'),
                $this->string($condition, 'expectedValue'),
            );
        }

        return new QuestionSnapshot(
            $this->string($data, 'key'),
            $this->string($data, 'label'),
            $this->string($data, 'type'),
            $this->int($data, 'position'),
            $this->nullableString($data, 'helpText'),
            $this->bool($data, 'required'),
            $this->nullableInt($data, 'min'),
            $this->nullableInt($data, 'max'),
            $this->nullableString($data, 'regex'),
            $options,
            $conditions,
        );
    }

    /**
     * @param array<mixed> $data
     * @return list<array<mixed>>
     */
    private function rows(array $data, string $key): array
    {
        $rows = $data[$key] ?? null;

        if (!is_array($rows)) {
            throw $this->invalid($key);
        }

        $result = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw $this->invalid($key);
            }

            $result[] = $row;
        }

        return $result;
    }

    /**
     * @param array<mixed> $data
     */
    private function string(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        if (!is_string($value)) {
            throw $this->invalid($key);
        }

        return $value;
    }

    /**
     * @param array<mixed> $data
     */
    private function nullableString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw $this->invalid($key);
        }

        return $value;
    }

    /**
     * @param array<mixed> $data
     */
    private function int(array $data, string $key): int
    {
        $value = $data[$key] ?? null;

        if (!is_int($value)) {
            throw $this->invalid($key);
        }

        return $value;
    }

    /**
     * @param array<mixed> $data
     */
    private function nullableInt(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        if ($value === null) {
            return null;
        }

        if (!is_int($value)) {
            throw $this->invalid($key);
        }

        return $value;
    }

    /**
     * @param array<mixed> $data
     */
    private function float(array $data, string $key): float
    {
        $value = $data[$key] ?? null;

        if (!is_int($value) && !is_float($value)) {
            throw $this->invalid($key);
        }

        return (float) $value;
    }

    /**
     * @param array<mixed> $data
     */
    private function bool(array $data, string $key): bool
    {
        $value = $data[$key] ?? null;

        if (!is_bool($value)) {
            throw $this->invalid($key);
        }

        return $value;
    }

    private function invalid(string $key): InvalidArgumentException
    {
        return new InvalidArgumentException(sprintf('Invalid "%s" in structure snapshot JSON.', $key));
    }
}
