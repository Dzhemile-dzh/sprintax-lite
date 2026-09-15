<?php

declare(strict_types=1);

namespace App\Application\Audit;

use App\Domain\Audit\Entity\QuestionnaireRevision;
use App\Domain\Audit\QuestionnaireStructureSnapshot;
use App\Domain\Audit\Repository\QuestionnaireRevisionRepositoryInterface;
use App\Domain\Audit\ValueObject\RevisionAction;
use App\Domain\Questionnaire\Entity\Questionnaire;
use DateTimeImmutable;

final class RecordQuestionnaireRevision
{
    public function __construct(
        private readonly QuestionnaireRevisionRepositoryInterface $revisions,
        private readonly QuestionnaireStructureSnapshot $snapshots,
        private readonly ActorProvider $actors,
    ) {
    }

    public function execute(Questionnaire $questionnaire, RevisionAction $action, string $summary): void
    {
        $this->revisions->add(QuestionnaireRevision::record(
            bin2hex(random_bytes(16)),
            $questionnaire->id(),
            $this->revisions->nextVersion($questionnaire->id()),
            $action,
            $summary,
            $this->actors->currentActor(),
            $this->snapshots->of($questionnaire),
            new DateTimeImmutable(),
        ));
    }
}
