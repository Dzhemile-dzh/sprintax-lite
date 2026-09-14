<?php

declare(strict_types=1);

namespace App\Application\Submission\Finalize;

use App\Application\Pdf\PdfGenerationScheduler;
use App\Domain\Submission\Exception\InvalidSubmission;
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

    public function execute(string $submissionId, string $actorUserId, bool $actorIsAdmin): void
    {
        $submission = $this->submissions->get($submissionId);

        if (!$actorIsAdmin && $submission->user()->id() !== $actorUserId) {
            throw InvalidSubmission::notOwner();
        }

        $this->completeness->assertComplete($submission);
        $submission->finalize(new DateTimeImmutable());
        $this->submissions->save($submission);
        $this->pdfGenerationScheduler->schedule($submission->id());
    }
}
