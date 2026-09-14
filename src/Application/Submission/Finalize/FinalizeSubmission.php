<?php

declare(strict_types=1);

namespace App\Application\Submission\Finalize;

use App\Application\Pdf\PdfGenerationScheduler;
use App\Domain\Submission\Repository\SubmissionRepositoryInterface;
use App\Domain\Submission\SubmissionCompleteness;
use DateTimeImmutable;

final class FinalizeSubmission
{
    public function __construct(
        private readonly SubmissionRepositoryInterface $submissions,
        private readonly SubmissionCompleteness $completeness,
        private readonly PdfGenerationScheduler $pdfGenerationScheduler,
    ) {
    }

    public function execute(string $submissionId): void
    {
        $submission = $this->submissions->get($submissionId);
        $this->completeness->assertComplete($submission);
        $submission->finalize(new DateTimeImmutable());
        $this->submissions->save($submission);
        $this->pdfGenerationScheduler->schedule($submission->id());
    }
}
