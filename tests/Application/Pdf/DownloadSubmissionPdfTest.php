<?php

declare(strict_types=1);

namespace App\Tests\Application\Pdf;

use App\Application\Pdf\DownloadSubmissionPdf;
use App\Domain\Questionnaire\Entity\Questionnaire;
use App\Domain\Questionnaire\ValueObject\FormType;
use App\Domain\Questionnaire\ValueObject\QuestionType;
use App\Domain\Submission\Entity\QuestionnaireSubmission;
use App\Domain\Submission\Exception\InvalidSubmission;
use App\Domain\User\Entity\User;
use App\Domain\User\ValueObject\Email;
use App\Infrastructure\Filesystem\LocalFileStorage;
use App\Tests\Support\InMemorySubmissionRepository;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class DownloadSubmissionPdfTest extends TestCase
{
    public function testItReturnsTheAbsolutePathWhenThePdfIsReady(): void
    {
        $outputDirectory = $this->outputDirectory();
        $path = $outputDirectory.DIRECTORY_SEPARATOR.'sub-1.pdf';
        file_put_contents($path, '%PDF-1.4');

        $resolved = $this->useCase($this->readySubmission(), $outputDirectory)->execute('sub-1', 'user-1', false);

        self::assertSame($path, $resolved);
    }

    public function testAnAdminCanDownloadAnotherUsersPdf(): void
    {
        $outputDirectory = $this->outputDirectory();
        $path = $outputDirectory.DIRECTORY_SEPARATOR.'sub-1.pdf';
        file_put_contents($path, '%PDF-1.4');

        $resolved = $this->useCase($this->readySubmission(), $outputDirectory)->execute('sub-1', 'admin-1', true);

        self::assertSame($path, $resolved);
    }

    public function testItRejectsADifferentClient(): void
    {
        $this->expectException(InvalidSubmission::class);
        $this->useCase($this->readySubmission(), $this->outputDirectory())->execute('sub-1', 'user-other', false);
    }

    public function testItRejectsATraversalSubmissionId(): void
    {
        $questionnaire = Questionnaire::create('q-1', '1040-NR', FormType::Form1040Nr);
        $questionnaire->addStep('step-1', 'Personal');
        $questionnaire->addQuestion('step-1', 'q-name', 'first_name', 'First name', QuestionType::ShortText);
        $submission = QuestionnaireSubmission::start(
            '../secret',
            $questionnaire,
            User::registerClient('user-1', new Email('client@example.test'), 'hashed-password'),
            new DateTimeImmutable('2026-01-01T10:00:00+00:00'),
        );
        $submission->finalize(new DateTimeImmutable('2026-01-01T11:00:00+00:00'));
        $submission->markPdfReady(
            new DateTimeImmutable('2026-01-01T11:05:00+00:00'),
            '../secret.pdf',
        );

        $this->expectException(InvalidSubmission::class);
        $this->useCase($submission, $this->outputDirectory())->execute('../secret', 'user-1', false);
    }

    public function testItRejectsSubmissionsThatAreNotPdfReady(): void
    {
        $this->expectException(InvalidSubmission::class);
        $this->useCase($this->finalizedSubmission(), $this->outputDirectory())->execute('sub-1', 'user-1', false);
    }

    public function testItRejectsAMissingPdfFile(): void
    {
        $this->expectException(InvalidSubmission::class);
        $this->useCase($this->readySubmission(), $this->outputDirectory())->execute('sub-1', 'user-1', false);
    }

    public function testItIgnoresAStoredPathThatDoesNotMatchTheSubmission(): void
    {
        $outputDirectory = $this->outputDirectory();
        file_put_contents($outputDirectory.DIRECTORY_SEPARATOR.'other.pdf', '%PDF-other');

        $submission = $this->finalizedSubmission();
        $submission->markPdfReady(
            new DateTimeImmutable('2026-01-01T11:05:00+00:00'),
            'other.pdf',
        );

        $this->expectException(InvalidSubmission::class);
        $this->useCase($submission, $outputDirectory)->execute('sub-1', 'user-1', false);
    }

    private function useCase(
        QuestionnaireSubmission $submission,
        string $outputDirectory,
    ): DownloadSubmissionPdf {
        return new DownloadSubmissionPdf(
            InMemorySubmissionRepository::with($submission),
            new LocalFileStorage(),
            $outputDirectory,
        );
    }

    private function readySubmission(): QuestionnaireSubmission
    {
        $submission = $this->finalizedSubmission();
        $submission->markPdfReady(
            new DateTimeImmutable('2026-01-01T11:05:00+00:00'),
            'sub-1.pdf',
        );

        return $submission;
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
        $questionnaire->addQuestion('step-1', 'q-name', 'first_name', 'First name', QuestionType::ShortText);

        return QuestionnaireSubmission::start(
            'sub-1',
            $questionnaire,
            User::registerClient('user-1', new Email('client@example.test'), 'hashed-password'),
            new DateTimeImmutable('2026-01-01T10:00:00+00:00'),
        );
    }

    private function outputDirectory(): string
    {
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sprintax-pdf-dl-'.uniqid('', true);
        mkdir($directory, 0775, true);

        return $directory;
    }
}
