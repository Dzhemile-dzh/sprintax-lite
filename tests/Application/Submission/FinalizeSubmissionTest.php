<?php

declare(strict_types=1);

namespace App\Tests\Application\Submission;

use App\Application\Pdf\PdfGenerationScheduler;
use App\Application\Submission\Finalize\FinalizeSubmission;
use App\Domain\Questionnaire\Entity\Questionnaire;
use App\Domain\Questionnaire\QuestionVisibilityEvaluator;
use App\Domain\Questionnaire\ValueObject\FormType;
use App\Domain\Questionnaire\ValueObject\QuestionType;
use App\Domain\Questionnaire\ValueObject\QuestionValidation;
use App\Domain\Questionnaire\ValueObject\VisibilityCondition;
use App\Domain\Questionnaire\ValueObject\VisibilityOperator;
use App\Domain\Questionnaire\ValueObject\VisibilityRule;
use App\Domain\Submission\Entity\QuestionnaireSubmission;
use App\Domain\Submission\Exception\InvalidSubmission;
use App\Domain\Submission\Exception\SubmissionNotFound;
use App\Domain\Submission\QuestionAnswerValidator;
use App\Domain\Submission\Repository\SubmissionRepositoryInterface;
use App\Domain\Submission\SubmissionCompleteness;
use App\Domain\Submission\ValueObject\AnswerValue;
use App\Domain\Submission\ValueObject\SubmissionStatus;
use App\Domain\User\Entity\User;
use App\Domain\User\ValueObject\Email;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class FinalizeSubmissionTest extends TestCase
{
    public function testItRejectsAnIncompleteSubmissionAndDoesNotSchedulePdfGeneration(): void
    {
        $submission = $this->submission();
        $scheduler = new RecordingPdfGenerationScheduler();

        $this->expectException(InvalidSubmission::class);
        try {
            $this->useCase($submission, $scheduler)->execute('sub-1', 'user-1', false);
        } finally {
            self::assertSame([], $scheduler->submissionIds);
            self::assertSame(SubmissionStatus::InProgress, $submission->status());
        }
    }

    public function testItRejectsAnInvalidAnswerAndDoesNotSchedulePdfGeneration(): void
    {
        $submission = $this->submission();
        $name = $submission->questionnaire()->findQuestionByKey('first_name');
        $wages = $submission->questionnaire()->findQuestionByKey('wages');
        self::assertNotNull($name);
        self::assertNotNull($wages);
        $now = new DateTimeImmutable('2026-01-01T10:05:00+00:00');
        $submission->recordAnswer($name, AnswerValue::text('Ada'), $now);
        $submission->recordAnswer($wages, AnswerValue::text('99999'), $now);
        $scheduler = new RecordingPdfGenerationScheduler();

        $this->expectException(InvalidSubmission::class);
        try {
            $this->useCase($submission, $scheduler)->execute('sub-1', 'user-1', false);
        } finally {
            self::assertSame([], $scheduler->submissionIds);
            self::assertSame(SubmissionStatus::InProgress, $submission->status());
        }
    }

    public function testItFinalizesACompleteSubmissionAndSchedulesPdfGeneration(): void
    {
        $submission = $this->complete($this->submission());
        $scheduler = new RecordingPdfGenerationScheduler();

        $this->useCase($submission, $scheduler)->execute('sub-1', 'user-1', false);

        self::assertSame(SubmissionStatus::Finalized, $submission->status());
        self::assertSame(['sub-1'], $scheduler->submissionIds);
    }

    public function testHiddenRequiredQuestionsAreNotRequiredToFinalize(): void
    {
        $submission = $this->submission();
        $name = $submission->questionnaire()->findQuestionByKey('first_name');
        $married = $submission->questionnaire()->findQuestionByKey('married');
        $wages = $submission->questionnaire()->findQuestionByKey('wages');
        self::assertNotNull($name);
        self::assertNotNull($married);
        self::assertNotNull($wages);
        $now = new DateTimeImmutable('2026-01-01T10:05:00+00:00');
        $submission->recordAnswer($name, AnswerValue::text('Ada'), $now);
        $submission->recordAnswer($married, AnswerValue::text('no'), $now);
        $submission->recordAnswer($wages, AnswerValue::text('50'), $now);
        $scheduler = new RecordingPdfGenerationScheduler();

        $this->useCase($submission, $scheduler)->execute('sub-1', 'user-1', false);

        self::assertSame(SubmissionStatus::Finalized, $submission->status());
        self::assertSame(['sub-1'], $scheduler->submissionIds);
        self::assertArrayNotHasKey('spouse_name', $submission->answersByQuestionKey());
    }

    public function testItRejectsADifferentClientAndDoesNotSchedulePdfGeneration(): void
    {
        $submission = $this->complete($this->submission());
        $scheduler = new RecordingPdfGenerationScheduler();

        $this->expectException(InvalidSubmission::class);
        try {
            $this->useCase($submission, $scheduler)->execute('sub-1', 'user-other', false);
        } finally {
            self::assertSame([], $scheduler->submissionIds);
            self::assertSame(SubmissionStatus::InProgress, $submission->status());
        }
    }

    private function useCase(
        QuestionnaireSubmission $submission,
        PdfGenerationScheduler $scheduler,
    ): FinalizeSubmission {
        return new FinalizeSubmission(
            new FinalizeInMemorySubmissionRepository(['sub-1' => $submission]),
            new SubmissionCompleteness(new QuestionVisibilityEvaluator(), new QuestionAnswerValidator()),
            $scheduler,
        );
    }

    private function complete(QuestionnaireSubmission $submission): QuestionnaireSubmission
    {
        $name = $submission->questionnaire()->findQuestionByKey('first_name');
        $married = $submission->questionnaire()->findQuestionByKey('married');
        $spouse = $submission->questionnaire()->findQuestionByKey('spouse_name');
        $wages = $submission->questionnaire()->findQuestionByKey('wages');
        self::assertNotNull($name);
        self::assertNotNull($married);
        self::assertNotNull($spouse);
        self::assertNotNull($wages);
        $now = new DateTimeImmutable('2026-01-01T10:05:00+00:00');
        $submission->recordAnswer($name, AnswerValue::text('Ada'), $now);
        $submission->recordAnswer($married, AnswerValue::text('yes'), $now);
        $submission->recordAnswer($spouse, AnswerValue::text('Charles'), $now);
        $submission->recordAnswer($wages, AnswerValue::text('50'), $now);

        return $submission;
    }

    private function submission(): QuestionnaireSubmission
    {
        $questionnaire = Questionnaire::create('q-1', '1040-NR', FormType::Form1040Nr);
        $questionnaire->addStep('step-1', 'Personal');
        $questionnaire->addQuestion(
            'step-1',
            'q-name',
            'first_name',
            'First name',
            QuestionType::ShortText,
            null,
            QuestionValidation::required(),
        );
        $questionnaire->addQuestion(
            'step-1',
            'q-married',
            'married',
            'Married?',
            QuestionType::YesNo,
            null,
            QuestionValidation::required(),
        );
        $questionnaire->addQuestion(
            'step-1',
            'q-spouse',
            'spouse_name',
            'Spouse name',
            QuestionType::ShortText,
            null,
            QuestionValidation::required(),
            new VisibilityRule([
                new VisibilityCondition('married', VisibilityOperator::Equals, 'yes'),
            ]),
        );
        $questionnaire->addQuestion(
            'step-1',
            'q-wages',
            'wages',
            'Wages',
            QuestionType::Number,
            null,
            new QuestionValidation(required: true, min: 0, max: 100),
        );

        return QuestionnaireSubmission::start(
            'sub-1',
            $questionnaire,
            User::registerClient('user-1', new Email('client@example.test'), 'hashed-password'),
            new DateTimeImmutable('2026-01-01T10:00:00+00:00'),
        );
    }
}

final class RecordingPdfGenerationScheduler implements PdfGenerationScheduler
{
    /** @var list<string> */
    public array $submissionIds = [];

    public function schedule(string $submissionId): void
    {
        $this->submissionIds[] = $submissionId;
    }
}

final class FinalizeInMemorySubmissionRepository implements SubmissionRepositoryInterface
{
    /**
     * @param array<string, QuestionnaireSubmission> $submissions
     */
    public function __construct(
        private array $submissions,
    ) {
    }

    public function get(string $id): QuestionnaireSubmission
    {
        $submission = $this->submissions[$id] ?? null;

        if (!$submission instanceof QuestionnaireSubmission) {
            throw SubmissionNotFound::withId($id);
        }

        return $submission;
    }

    public function findByUserAndQuestionnaire(string $userId, string $questionnaireId): ?QuestionnaireSubmission
    {
        foreach ($this->submissions as $submission) {
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

        foreach ($this->submissions as $submission) {
            if ($submission->user()->id() === $userId) {
                $matches[] = $submission;
            }
        }

        return $matches;
    }

    /**
     * @return list<QuestionnaireSubmission>
     */
    public function findAllRecent(): array
    {
        return array_values($this->submissions);
    }

    public function existsForQuestionnaire(string $questionnaireId): bool
    {
        foreach ($this->submissions as $submission) {
            if ($submission->questionnaire()->id() === $questionnaireId) {
                return true;
            }
        }

        return false;
    }

    public function save(QuestionnaireSubmission $submission): void
    {
        $this->submissions[$submission->id()] = $submission;
    }
}
