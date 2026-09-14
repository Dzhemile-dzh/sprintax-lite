<?php

declare(strict_types=1);

namespace App\Application\Pdf;

use App\Domain\Filesystem\Contract\FileStorageInterface;
use App\Domain\Submission\Exception\InvalidSubmission;
use App\Domain\Submission\Repository\SubmissionRepositoryInterface;
use App\Domain\Submission\ValueObject\SubmissionStatus;
use DateTimeImmutable;

final class EmailSubmissionPdf
{
    public function __construct(
        private readonly SubmissionRepositoryInterface $submissions,
        private readonly FileStorageInterface $fileStorage,
        private readonly SubmissionPdfMailer $mailer,
        private readonly string $outputDirectory,
    ) {
    }

    public function execute(string $submissionId): void
    {
        $submission = $this->submissions->get($submissionId);

        if ($submission->status() !== SubmissionStatus::PdfReady) {
            throw InvalidSubmission::cannotEmailPdf($submission->status());
        }

        if ($submission->pdfWasEmailed()) {
            return;
        }

        $path = PdfOutputPath::absolute($this->outputDirectory, $submission->id());

        if (!$this->fileStorage->isReadable($path)) {
            throw InvalidSubmission::pdfFileMissing();
        }

        $this->mailer->send(
            $submission->user()->email()->value(),
            $submission->questionnaire()->name(),
            $path,
            PdfOutputPath::fileName($submission->id()),
        );

        $submission->markPdfEmailed(new DateTimeImmutable());
        $this->submissions->save($submission);
    }
}
