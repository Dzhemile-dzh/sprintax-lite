<?php

declare(strict_types=1);

namespace App\Tests\Application\Pdf;

use App\Application\Pdf\EmailSubmissionPdf;
use App\Domain\Questionnaire\Entity\Questionnaire;
use App\Domain\Questionnaire\ValueObject\FormType;
use App\Domain\Questionnaire\ValueObject\QuestionType;
use App\Domain\Submission\Entity\QuestionnaireSubmission;
use App\Domain\Submission\Exception\InvalidSubmission;
use App\Domain\User\Entity\User;
use App\Domain\User\ValueObject\Email;
use App\Infrastructure\Filesystem\LocalFileStorage;
use App\Tests\Support\InMemorySubmissionRepository;
use App\Tests\Support\RecordingSubmissionPdfMailer;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class EmailSubmissionPdfTest extends TestCase
{
    public function testItEmailsAReadyPdfOnce(): void
    {
        $submission = $this->readySubmission();
        $outputDirectory = $this->outputDirectoryWithPdf($submission->id());
        $mailer = new RecordingSubmissionPdfMailer();
        $repository = InMemorySubmissionRepository::with($submission);
        $useCase = new EmailSubmissionPdf($repository, new LocalFileStorage(), $mailer, $outputDirectory);

        $useCase->execute($submission->id());
        $useCase->execute($submission->id());

        self::assertCount(1, $mailer->sent);
        self::assertTrue($submission->pdfWasEmailed());
        self::assertSame(1, $repository->saveCount);
        self::assertSame('client@example.test', $mailer->sent[0]['recipientEmail']);
        self::assertSame('1040-NR', $mailer->sent[0]['questionnaireName']);
        self::assertSame($outputDirectory.DIRECTORY_SEPARATOR.'sub-1.pdf', $mailer->sent[0]['absolutePdfPath']);
        self::assertSame('sub-1.pdf', $mailer->sent[0]['downloadFileName']);
    }

    public function testItRejectsASubmissionThatIsNotPdfReady(): void
    {
        $submission = $this->submission();
        $useCase = new EmailSubmissionPdf(
            InMemorySubmissionRepository::with($submission),
            new LocalFileStorage(),
            new RecordingSubmissionPdfMailer(),
            sys_get_temp_dir(),
        );

        $this->expectException(InvalidSubmission::class);
        $useCase->execute($submission->id());
    }

    public function testItRejectsAMissingPdfFile(): void
    {
        $submission = $this->readySubmission();
        $useCase = new EmailSubmissionPdf(
            InMemorySubmissionRepository::with($submission),
            new LocalFileStorage(),
            new RecordingSubmissionPdfMailer(),
            sys_get_temp_dir().DIRECTORY_SEPARATOR.'sprintax-pdf-missing-'.uniqid('', true),
        );

        $this->expectException(InvalidSubmission::class);
        $useCase->execute($submission->id());
    }

    private function outputDirectoryWithPdf(string $submissionId): string
    {
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sprintax-pdf-mail-'.uniqid('', true);
        mkdir($directory, 0775, true);
        file_put_contents($directory.DIRECTORY_SEPARATOR.$submissionId.'.pdf', '%PDF-1.4');

        return $directory;
    }

    private function readySubmission(): QuestionnaireSubmission
    {
        $submission = $this->submission();
        $submission->finalize(new DateTimeImmutable('2026-01-01T11:00:00+00:00'));
        $submission->markPdfReady(new DateTimeImmutable('2026-01-01T11:05:00+00:00'), 'sub-1.pdf');

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
}
