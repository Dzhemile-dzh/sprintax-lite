<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Application\Audit\RecordQuestionnaireRevision;
use App\Domain\Audit\QuestionnaireStructureSnapshot;

final class TestRevisionRecorder
{
    public static function create(
        ?InMemoryQuestionnaireRevisionRepository $revisions = null,
    ): RecordQuestionnaireRevision {
        return new RecordQuestionnaireRevision(
            $revisions ?? new InMemoryQuestionnaireRevisionRepository(),
            new QuestionnaireStructureSnapshot(),
            FixedActorProvider::admin(),
        );
    }
}
