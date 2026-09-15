<?php

declare(strict_types=1);

namespace App\Application\Audit;

use App\Domain\Audit\Entity\QuestionnaireRevision;
use App\Domain\Audit\Repository\QuestionnaireRevisionRepositoryInterface;

final class ListQuestionnaireRevisions
{
    public function __construct(
        private readonly QuestionnaireRevisionRepositoryInterface $revisions,
    ) {
    }

    /**
     * @return list<QuestionnaireRevision>
     */
    public function execute(string $questionnaireId): array
    {
        return $this->revisions->forQuestionnaire($questionnaireId);
    }
}
