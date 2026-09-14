<?php

declare(strict_types=1);

namespace App\Infrastructure\Messenger;

use App\Application\Pdf\PdfGenerationScheduler;
use App\Infrastructure\Messenger\Message\GenerateSubmissionPdfMessage;
use Symfony\Component\Messenger\MessageBusInterface;

final class MessengerPdfGenerationScheduler implements PdfGenerationScheduler
{
    public function __construct(
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function schedule(string $submissionId): void
    {
        $this->bus->dispatch(new GenerateSubmissionPdfMessage($submissionId));
    }
}
