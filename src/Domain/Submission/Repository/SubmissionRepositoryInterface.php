<?php

declare(strict_types=1);

namespace App\Domain\Submission\Repository;

use App\Domain\Submission\Entity\QuestionnaireSubmission;

interface SubmissionRepositoryInterface
{
    public function get(string $id): QuestionnaireSubmission;

    public function findByUserAndQuestionnaire(string $userId, string $questionnaireId): ?QuestionnaireSubmission;

    /**
     * @return list<QuestionnaireSubmission>
     */
    public function findForUser(string $userId): array;

    public function existsForQuestionnaire(string $questionnaireId): bool;

    public function save(QuestionnaireSubmission $submission): void;
}
