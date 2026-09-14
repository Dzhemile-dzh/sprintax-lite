<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Messenger;

use App\Application\Calculation\CalculateSubmission;
use App\Application\Pdf\GenerateSubmissionPdf;
use App\Domain\Pdf\Contract\PdfGeneratorInterface;
use App\Domain\Pdf\Exception\PdfGenerationFailed;
use App\Domain\Questionnaire\Entity\QuestionMapping;
use App\Domain\Questionnaire\Entity\Questionnaire;
use App\Domain\Questionnaire\QuestionVisibilityEvaluator;
use App\Domain\Questionnaire\ValueObject\FormType;
use App\Domain\Questionnaire\ValueObject\PdfCoordinates;
use App\Domain\Questionnaire\ValueObject\QuestionType;
use App\Domain\Submission\Entity\QuestionnaireSubmission;
use App\Domain\Submission\ValueObject\AnswerValue;
use App\Domain\Submission\ValueObject\SubmissionStatus;
use App\Domain\User\Entity\User;
use App\Domain\User\ValueObject\Email;
use App\Infrastructure\Calculation\Form1040NrCalculator;
use App\Infrastructure\Filesystem\LocalFileStorage;
use App\Infrastructure\Messenger\GenerateSubmissionPdfHandler;
use App\Infrastructure\Messenger\Message\GenerateSubmissionPdfMessage;
use App\Tests\Support\FailingPdfGenerator;
use App\Tests\Support\InMemorySubmissionRepository;
use App\Tests\Support\RecordingPdfGenerator;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

final class GenerateSubmissionPdfHandlerTest extends TestCase
{
    private InMemorySubmissionRepository $submissions;

    private string $outputDirectory;

    public function testItMarksTheSubmissionPdfReady(): void
    {
        $submission = $this->finalizedSubmission();
        $recorder = new RecordingPdfGenerator();
        $handler = $this->handler($submission, $recorder);

        $handler(new GenerateSubmissionPdfMessage('sub-1'));

        self::assertSame(SubmissionStatus::PdfReady, $submission->status());
        self::assertSame('sub-1.pdf', $submission->pdfPath());
        self::assertSame(1, $recorder->calls);
        self::assertSame(1, $this->submissions->saveCount);
        self::assertFileExists($this->outputDirectory.DIRECTORY_SEPARATOR.'sub-1.pdf');
    }

    public function testItIsIdempotentWhenProcessedTwice(): void
    {
        $submission = $this->finalizedSubmission();
        $recorder = new RecordingPdfGenerator();
        $handler = $this->handler($submission, $recorder);

        $handler(new GenerateSubmissionPdfMessage('sub-1'));
        $handler(new GenerateSubmissionPdfMessage('sub-1'));

        self::assertSame(1, $recorder->calls);
        self::assertSame(1, $this->submissions->saveCount);
        self::assertSame(SubmissionStatus::PdfReady, $submission->status());
    }

    public function testMissingSubmissionIsUnrecoverable(): void
    {
        $handler = $this->handler($this->finalizedSubmission(), new RecordingPdfGenerator());

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $handler(new GenerateSubmissionPdfMessage('missing'));
    }

    public function testInProgressSubmissionIsUnrecoverable(): void
    {
        $handler = $this->handler($this->submission(), new RecordingPdfGenerator());

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $handler(new GenerateSubmissionPdfMessage('sub-1'));
    }

    public function testMissingTemplateIsUnrecoverable(): void
    {
        $handler = $this->handler(
            $this->finalizedSubmission(),
            new RecordingPdfGenerator(),
            sys_get_temp_dir().DIRECTORY_SEPARATOR.'sprintax-pdf-missing-'.uniqid('', true),
        );

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $handler(new GenerateSubmissionPdfMessage('sub-1'));
    }

    public function testOverlayFailuresRemainRetryable(): void
    {
        $handler = $this->handler($this->finalizedSubmission(), new FailingPdfGenerator());

        $this->expectException(PdfGenerationFailed::class);
        $handler(new GenerateSubmissionPdfMessage('sub-1'));
    }

    private function handler(
        QuestionnaireSubmission $submission,
        PdfGeneratorInterface $generator,
        ?string $templatesDirectory = null,
    ): GenerateSubmissionPdfHandler {
        $this->submissions = InMemorySubmissionRepository::with($submission);
        $this->outputDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sprintax-pdf-out-'.uniqid('', true);
        $templates = $templatesDirectory ?? $this->templatesDirectoryWith('1040-nr.pdf');

        return new GenerateSubmissionPdfHandler(new GenerateSubmissionPdf(
            $this->submissions,
            new CalculateSubmission($this->submissions, [new Form1040NrCalculator()]),
            $generator,
            new QuestionVisibilityEvaluator(),
            new LocalFileStorage(),
            $templates,
            $this->outputDirectory,
        ));
    }

    private function finalizedSubmission(): QuestionnaireSubmission
    {
        $submission = $this->submission();
        $submission->finalize(new DateTimeImmutable('2026-01-01T11:00:00+00:00'));

        return $submission;
    }

    private function submission(): QuestionnaireSubmission
    {
        $questionnaire = Questionnaire::create('q-1', '1040-NR', FormType::Form1040Nr);
        $questionnaire->addStep('step-1', 'Personal');
        $firstName = $questionnaire->addQuestion(
            'step-1',
            'q-name',
            'first_name',
            'First name',
            QuestionType::ShortText,
        );
        $questionnaire->addMapping(QuestionMapping::forQuestion(
            'map-name',
            $firstName->key(),
            new PdfCoordinates(1, 20.5, 40.25, 11),
        ));

        $now = new DateTimeImmutable('2026-01-01T10:00:00+00:00');
        $submission = QuestionnaireSubmission::start(
            'sub-1',
            $questionnaire,
            User::registerClient('user-1', new Email('client@example.test'), 'hashed-password'),
            $now,
        );
        $submission->recordAnswer($firstName, AnswerValue::text('Ada'), $now);

        return $submission;
    }

    private function templatesDirectoryWith(string $fileName): string
    {
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sprintax-pdf-tpl-'.uniqid('', true);
        mkdir($directory, 0775, true);
        file_put_contents($directory.DIRECTORY_SEPARATOR.$fileName, '%PDF-1.4 placeholder');

        return $directory;
    }
}
