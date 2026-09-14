<?php

declare(strict_types=1);

namespace App\Application\Pdf;

use App\Domain\Filesystem\Contract\FileStorageInterface;
use App\Domain\Submission\Exception\InvalidSubmission;
use App\Domain\Submission\Repository\SubmissionRepositoryInterface;
use App\Domain\Submission\ValueObject\SubmissionStatus;

final class DownloadSubmissionPdf
{
    public function __construct(
        private readonly SubmissionRepositoryInterface $submissions,
        private readonly FileStorageInterface $fileStorage,
        private readonly string $outputDirectory,
    ) {
    }

    public function execute(string $submissionId): string
    {
        $submission = $this->submissions->get($submissionId);

        if ($submission->status() !== SubmissionStatus::PdfReady) {
            throw InvalidSubmission::pdfNotReady($submission->status());
        }

        $path = $this->outputDirectory.DIRECTORY_SEPARATOR.$submission->id().'.pdf';

        if (!$this->fileStorage->isReadable($path)) {
            throw InvalidSubmission::pdfFileMissing();
        }

        return $path;
    }
}
