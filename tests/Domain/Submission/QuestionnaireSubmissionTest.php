<?php

declare(strict_types=1);

namespace App\Tests\Domain\Submission;

use App\Domain\Questionnaire\Entity\Questionnaire;
use App\Domain\Questionnaire\ValueObject\FormType;
use App\Domain\Questionnaire\ValueObject\QuestionType;
use App\Domain\Submission\Entity\QuestionnaireSubmission;
use App\Domain\Submission\Exception\InvalidSubmission;
use App\Domain\Submission\ValueObject\AnswerValue;
use App\Domain\Submission\ValueObject\SubmissionStatus;
use App\Domain\User\Entity\User;
use App\Domain\User\ValueObject\Email;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class QuestionnaireSubmissionTest extends TestCase
{
    public function testStartResumesOnTheFirstStepAndRecordsAnswersByQuestion(): void
    {
        $questionnaire = $this->questionnaire();
        $married = $questionnaire->findQuestionByKey('married');
        self::assertNotNull($married);

        $submission = QuestionnaireSubmission::start(
            'sub-1',
            $questionnaire,
            $this->client(),
            new DateTimeImmutable('2026-01-01T10:00:00+00:00'),
        );

        self::assertSame(SubmissionStatus::InProgress, $submission->status());
        self::assertFalse($submission->status()->isAwaitingPdf());
        self::assertSame('step-1', $submission->currentStepId());

        $submission->recordAnswer(
            $married,
            AnswerValue::text('yes'),
            new DateTimeImmutable('2026-01-01T10:05:00+00:00'),
        );
        $submission->recordAnswer(
            $married,
            AnswerValue::text('no'),
            new DateTimeImmutable('2026-01-01T10:06:00+00:00'),
        );

        self::assertCount(1, $submission->answers());
        self::assertSame('no', $submission->answerFor($married->id())?->value()->raw());
    }

    public function testDiscardAnswersNotInRemovesHiddenAnswers(): void
    {
        $questionnaire = $this->questionnaire();
        $married = $questionnaire->findQuestionByKey('married');
        self::assertNotNull($married);
        $note = $questionnaire->addQuestion('step-1', 'q-2', 'note', 'Note', QuestionType::ShortText);

        $submission = QuestionnaireSubmission::start(
            'sub-1',
            $questionnaire,
            $this->client(),
            new DateTimeImmutable('2026-01-01T10:00:00+00:00'),
        );
        $now = new DateTimeImmutable('2026-01-01T10:05:00+00:00');
        $submission->recordAnswer($married, AnswerValue::text('yes'), $now);
        $submission->recordAnswer($note, AnswerValue::text('keep me'), $now);

        $submission->discardAnswersNotIn([$note->id()], $now);

        self::assertNull($submission->answerFor($married->id()));
        self::assertSame('keep me', $submission->answerFor($note->id())?->value()->raw());
        self::assertArrayNotHasKey('married', $submission->answersByQuestionKey());
    }

    public function testFinalizeRequiresInProgressAndPdfReadyRequiresFinalized(): void
    {
        $submission = QuestionnaireSubmission::start(
            'sub-1',
            $this->questionnaire(),
            $this->client(),
            new DateTimeImmutable('2026-01-01T10:00:00+00:00'),
        );

        $submission->finalize(new DateTimeImmutable('2026-01-01T11:00:00+00:00'));
        self::assertSame(SubmissionStatus::Finalized, $submission->status());
        self::assertTrue($submission->status()->isAwaitingPdf());

        $this->expectException(InvalidSubmission::class);
        $submission->finalize(new DateTimeImmutable('2026-01-01T11:01:00+00:00'));
    }

    public function testMarkPdfReadyRequiresFinalizedAndStoresThePath(): void
    {
        $submission = QuestionnaireSubmission::start(
            'sub-1',
            $this->questionnaire(),
            $this->client(),
            new DateTimeImmutable('2026-01-01T10:00:00+00:00'),
        );

        $this->expectException(InvalidSubmission::class);
        $submission->markPdfReady(
            new DateTimeImmutable('2026-01-01T11:00:00+00:00'),
            '/tmp/sub-1.pdf',
        );
    }

    public function testMarkPdfReadyStoresThePathAndAllowsRegeneration(): void
    {
        $submission = QuestionnaireSubmission::start(
            'sub-1',
            $this->questionnaire(),
            $this->client(),
            new DateTimeImmutable('2026-01-01T10:00:00+00:00'),
        );

        $submission->finalize(new DateTimeImmutable('2026-01-01T11:00:00+00:00'));
        $submission->markPdfReady(
            new DateTimeImmutable('2026-01-01T11:05:00+00:00'),
            '/tmp/sub-1.pdf',
        );

        self::assertSame(SubmissionStatus::PdfReady, $submission->status());
        self::assertFalse($submission->status()->isAwaitingPdf());
        self::assertSame('/tmp/sub-1.pdf', $submission->pdfPath());

        $submission->markPdfReady(
            new DateTimeImmutable('2026-01-01T11:10:00+00:00'),
            '/tmp/sub-1-again.pdf',
        );

        self::assertSame(SubmissionStatus::PdfReady, $submission->status());
        self::assertSame('/tmp/sub-1-again.pdf', $submission->pdfPath());
    }

    public function testMarkPdfEmailedRejectsInProgressSubmissions(): void
    {
        $submission = QuestionnaireSubmission::start(
            'sub-1',
            $this->questionnaire(),
            $this->client(),
            new DateTimeImmutable('2026-01-01T10:00:00+00:00'),
        );

        $this->expectException(InvalidSubmission::class);
        $submission->markPdfEmailed(new DateTimeImmutable('2026-01-01T11:00:00+00:00'));
    }

    public function testMarkPdfEmailedStoresTheTimestampOnce(): void
    {
        $submission = QuestionnaireSubmission::start(
            'sub-1',
            $this->questionnaire(),
            $this->client(),
            new DateTimeImmutable('2026-01-01T10:00:00+00:00'),
        );
        $submission->finalize(new DateTimeImmutable('2026-01-01T11:00:00+00:00'));
        $submission->markPdfReady(
            new DateTimeImmutable('2026-01-01T11:05:00+00:00'),
            '/tmp/sub-1.pdf',
        );

        self::assertFalse($submission->pdfWasEmailed());

        $first = new DateTimeImmutable('2026-01-01T11:06:00+00:00');
        $submission->markPdfEmailed($first);
        self::assertTrue($submission->pdfWasEmailed());
        self::assertEquals($first, $submission->pdfEmailedAt());

        $submission->markPdfEmailed(new DateTimeImmutable('2026-01-01T11:07:00+00:00'));
        self::assertEquals($first, $submission->pdfEmailedAt());
    }

    public function testCannotStartWhenQuestionnaireHasNoSteps(): void
    {
        $this->expectException(InvalidSubmission::class);

        QuestionnaireSubmission::start(
            'sub-1',
            Questionnaire::create('q-1', 'Empty', FormType::Form1040Nr),
            $this->client(),
            new DateTimeImmutable('2026-01-01T10:00:00+00:00'),
        );
    }

    private function questionnaire(): Questionnaire
    {
        $questionnaire = Questionnaire::create('q-1', '1040-NR', FormType::Form1040Nr);
        $questionnaire->addStep('step-1', 'Personal');
        $questionnaire->addQuestion('step-1', 'q-1', 'married', 'Married?', QuestionType::YesNo);

        return $questionnaire;
    }

    private function client(): User
    {
        return User::registerClient('user-1', new Email('client@example.test'), 'hashed-password');
    }
}
