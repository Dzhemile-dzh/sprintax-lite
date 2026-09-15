<?php

declare(strict_types=1);

namespace App\Application\Audit;

use App\Domain\Audit\Entity\QuestionnaireRevision;
use App\Domain\Audit\Exception\RevisionNotFound;
use App\Domain\Audit\Repository\QuestionnaireRevisionRepositoryInterface;

final class GetQuestionnaireRevision
{
    public function __construct(
        private readonly QuestionnaireRevisionRepositoryInterface $revisions,
    ) {
    }

    public function execute(string $questionnaireId, int $version): QuestionnaireRevision
    {
        $revision = $this->revisions->find($questionnaireId, $version);

        if ($revision === null) {
            throw RevisionNotFound::version($questionnaireId, $version);
        }

        return $revision;
    }
}
