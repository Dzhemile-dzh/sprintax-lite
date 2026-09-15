<?php

declare(strict_types=1);

namespace App\Domain\Audit\Repository;

use App\Domain\Audit\Entity\QuestionnaireRevision;

interface QuestionnaireRevisionRepositoryInterface
{
    public function add(QuestionnaireRevision $revision): void;

    public function nextVersion(string $questionnaireId): int;

    /**
     * Newest first.
     *
     * @return list<QuestionnaireRevision>
     */
    public function forQuestionnaire(string $questionnaireId): array;

    public function find(string $questionnaireId, int $version): ?QuestionnaireRevision;

    /**
     * Newest first, across every questionnaire.
     *
     * @return list<QuestionnaireRevision>
     */
    public function latest(int $limit): array;
}
