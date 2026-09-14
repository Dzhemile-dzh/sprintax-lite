<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Domain\Submission\Entity\QuestionnaireSubmission;
use App\Domain\Submission\Exception\SubmissionNotFound;
use App\Domain\Submission\Repository\SubmissionRepositoryInterface;

final class InMemorySubmissionRepository implements SubmissionRepositoryInterface
{
    public int $saveCount = 0;

    /**
     * @param array<string, QuestionnaireSubmission> $items
     */
    public function __construct(
        private array $items = [],
    ) {
    }

    public static function with(QuestionnaireSubmission $submission): self
    {
        return new self([$submission->id() => $submission]);
    }

    public function get(string $id): QuestionnaireSubmission
    {
        $submission = $this->items[$id] ?? null;

        if (!$submission instanceof QuestionnaireSubmission) {
            throw SubmissionNotFound::withId($id);
        }

        return $submission;
    }

    public function save(QuestionnaireSubmission $submission): void
    {
        ++$this->saveCount;
        $this->items[$submission->id()] = $submission;
    }

    public function findByUserAndQuestionnaire(string $userId, string $questionnaireId): ?QuestionnaireSubmission
    {
        foreach ($this->items as $submission) {
            if ($submission->user()->id() === $userId && $submission->questionnaire()->id() === $questionnaireId) {
                return $submission;
            }
        }

        return null;
    }

    /**
     * @return list<QuestionnaireSubmission>
     */
    public function findForUser(string $userId): array
    {
        $matches = [];

        foreach ($this->items as $submission) {
            if ($submission->user()->id() === $userId) {
                $matches[] = $submission;
            }
        }

        return $matches;
    }

    public function existsForQuestionnaire(string $questionnaireId): bool
    {
        foreach ($this->items as $submission) {
            if ($submission->questionnaire()->id() === $questionnaireId) {
                return true;
            }
        }

        return false;
    }
}
