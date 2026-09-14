<?php

declare(strict_types=1);

namespace App\Infrastructure\Messenger;

use App\Application\Pdf\GenerateSubmissionPdf;
use App\Infrastructure\Messenger\Message\GenerateSubmissionPdfMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GenerateSubmissionPdfHandler
{
    public function __construct(
        private readonly GenerateSubmissionPdf $generateSubmissionPdf,
    ) {
    }

    public function __invoke(GenerateSubmissionPdfMessage $message): void
    {
        $this->generateSubmissionPdf->execute($message->submissionId);
    }
}
