<?php

declare(strict_types=1);

namespace App\Tests\Application\Audit;

use App\Application\Audit\RecordQuestionnaireRevision;
use App\Application\Questionnaire\WriteSteps;
use App\Domain\Audit\QuestionnaireStructureSnapshot;
use App\Domain\Audit\ValueObject\RevisionAction;
use App\Domain\Audit\ValueObject\RevisionActor;
use App\Domain\Questionnaire\Entity\Questionnaire;
use App\Domain\Questionnaire\ValueObject\FormType;
use App\Tests\Support\FixedActorProvider;
use App\Tests\Support\InMemoryQuestionnaireRepository;
use App\Tests\Support\InMemoryQuestionnaireRevisionRepository;
use App\Tests\Support\InMemorySubmissionRepository;
use PHPUnit\Framework\TestCase;

final class RecordQuestionnaireRevisionTest extends TestCase
{
    public function testItNumbersVersionsPerQuestionnaireAndStampsTheActor(): void
    {
        $revisions = new InMemoryQuestionnaireRevisionRepository();
        $recorder = $this->recorder($revisions);
        $first = Questionnaire::create('q-1', '1040-NR', FormType::Form1040Nr);
        $second = Questionnaire::create('q-2', 'W-8BEN', FormType::Form1040Nr);

        $recorder->execute($first, RevisionAction::QuestionnaireCreated, 'Created "1040-NR"');
        $recorder->execute($first, RevisionAction::QuestionnaireUpdated, 'Renamed');
        $recorder->execute($second, RevisionAction::QuestionnaireCreated, 'Created "W-8BEN"');

        self::assertSame([2, 1], array_map(
            static fn ($revision): int => $revision->version(),
            $revisions->forQuestionnaire('q-1'),
        ));
        self::assertSame(1, $revisions->forQuestionnaire('q-2')[0]->version());

        $latest = $revisions->forQuestionnaire('q-1')[0];
        self::assertSame('admin@example.test', $latest->actor()->email);
        self::assertSame('admin-1', $latest->actor()->id);
        self::assertSame(RevisionAction::QuestionnaireUpdated, $latest->action());
    }

    public function testItFallsBackToASystemActorWithoutALoggedInUser(): void
    {
        $revisions = new InMemoryQuestionnaireRevisionRepository();
        $recorder = new RecordQuestionnaireRevision(
            $revisions,
            new QuestionnaireStructureSnapshot(),
            new FixedActorProvider(RevisionActor::system()),
        );

        $recorder->execute(
            Questionnaire::create('q-1', '1040-NR', FormType::Form1040Nr),
            RevisionAction::QuestionnaireCreated,
            'Seeded',
        );

        self::assertSame('system', $revisions->forQuestionnaire('q-1')[0]->actor()->describe());
    }

    public function testStepUseCasesRecordTheStructureAfterEachChange(): void
    {
        $revisions = new InMemoryQuestionnaireRevisionRepository();
        $questionnaire = Questionnaire::create('q-1', '1040-NR', FormType::Form1040Nr);
        $questionnaires = InMemoryQuestionnaireRepository::with($questionnaire);
        $recorder = $this->recorder($revisions);

        $steps = new WriteSteps($questionnaires, new InMemorySubmissionRepository(), $recorder);
        $step = $steps->add('q-1', 'Personal');
        $steps->update('q-1', $step->id(), 'About you');
        $steps->remove('q-1', $step->id());

        $trail = array_reverse($revisions->forQuestionnaire('q-1'));

        self::assertSame(RevisionAction::StepAdded, $trail[0]->action());
        self::assertSame('Added step "Personal" at position 1', $trail[0]->summary());
        self::assertSame('Personal', $trail[0]->snapshot()->steps[0]->title);

        self::assertSame(RevisionAction::StepRenamed, $trail[1]->action());
        self::assertSame('About you', $trail[1]->snapshot()->steps[0]->title);

        self::assertSame(RevisionAction::StepRemoved, $trail[2]->action());
        self::assertSame('Removed step "About you"', $trail[2]->summary());
        self::assertSame([], $trail[2]->snapshot()->steps);
    }

    private function recorder(InMemoryQuestionnaireRevisionRepository $revisions): RecordQuestionnaireRevision
    {
        return new RecordQuestionnaireRevision(
            $revisions,
            new QuestionnaireStructureSnapshot(),
            FixedActorProvider::admin(),
        );
    }
}
