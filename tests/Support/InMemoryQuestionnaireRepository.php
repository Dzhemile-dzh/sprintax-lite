<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Domain\Questionnaire\Entity\Questionnaire;
use App\Domain\Questionnaire\Exception\QuestionnaireNotFound;
use App\Domain\Questionnaire\Repository\QuestionnaireRepositoryInterface;

final class InMemoryQuestionnaireRepository implements QuestionnaireRepositoryInterface
{
    /**
     * @param array<string, Questionnaire> $items
     */
    public function __construct(
        private array $items = [],
    ) {
    }

    public static function with(Questionnaire $questionnaire): self
    {
        return new self([$questionnaire->id() => $questionnaire]);
    }

    /**
     * @return list<Questionnaire>
     */
    public function all(): array
    {
        return array_values($this->items);
    }

    public function get(string $id): Questionnaire
    {
        $questionnaire = $this->items[$id] ?? null;

        if (!$questionnaire instanceof Questionnaire) {
            throw QuestionnaireNotFound::withId($id);
        }

        return $questionnaire;
    }

    public function save(Questionnaire $questionnaire): void
    {
        $this->items[$questionnaire->id()] = $questionnaire;
    }
}
