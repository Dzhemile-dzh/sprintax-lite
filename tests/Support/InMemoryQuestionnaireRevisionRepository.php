<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Domain\Audit\Entity\QuestionnaireRevision;
use App\Domain\Audit\Repository\QuestionnaireRevisionRepositoryInterface;

final class InMemoryQuestionnaireRevisionRepository implements QuestionnaireRevisionRepositoryInterface
{
    /**
     * @var list<QuestionnaireRevision>
     */
    private array $revisions = [];

    public function add(QuestionnaireRevision $revision): void
    {
        $this->revisions[] = $revision;
    }

    public function nextVersion(string $questionnaireId): int
    {
        $highest = 0;

        foreach ($this->revisions as $revision) {
            if ($revision->questionnaireId() === $questionnaireId) {
                $highest = max($highest, $revision->version());
            }
        }

        return $highest + 1;
    }

    /**
     * @return list<QuestionnaireRevision>
     */
    public function forQuestionnaire(string $questionnaireId): array
    {
        $matching = [];

        foreach ($this->revisions as $revision) {
            if ($revision->questionnaireId() === $questionnaireId) {
                $matching[] = $revision;
            }
        }

        usort(
            $matching,
            static fn (QuestionnaireRevision $left, QuestionnaireRevision $right): int
                => $right->version() <=> $left->version(),
        );

        return $matching;
    }

    public function find(string $questionnaireId, int $version): ?QuestionnaireRevision
    {
        foreach ($this->revisions as $revision) {
            if ($revision->questionnaireId() === $questionnaireId && $revision->version() === $version) {
                return $revision;
            }
        }

        return null;
    }

    /**
     * @return list<QuestionnaireRevision>
     */
    public function latest(int $limit): array
    {
        $ordered = array_reverse($this->revisions);

        return array_slice($ordered, 0, $limit);
    }
}
