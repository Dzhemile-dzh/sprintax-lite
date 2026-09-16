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
        private readonly PdfGenerationScheduler $pdfGenerationScheduler,
        private readonly string $outputDirectory,
    ) {
    }

    public function execute(string $submissionId, string $actorUserId, bool $actorIsAdmin): string
    {
        $submission = $this->submissions->get($submissionId);

        if (!$actorIsAdmin && $submission->user()->id() !== $actorUserId) {
            throw InvalidSubmission::notOwner();
        }

        if ($submission->status() !== SubmissionStatus::PdfReady) {
            throw InvalidSubmission::pdfNotReady($submission->status());
        }

        $path = PdfOutputPath::absolute($this->outputDirectory, $submission->id());

        if (!$this->fileStorage->isReadable($path)) {
            $this->pdfGenerationScheduler->schedule($submissionId);

            throw InvalidSubmission::pdfFileMissing();
        }

        return $path;
    }
}
