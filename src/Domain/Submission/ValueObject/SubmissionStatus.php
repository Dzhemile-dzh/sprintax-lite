<?php

declare(strict_types=1);

namespace App\Domain\Submission\ValueObject;

enum SubmissionStatus: string
{
    case InProgress = 'in_progress';
    case Finalized = 'finalized';
    case PdfReady = 'pdf_ready';

    public function isAwaitingPdf(): bool
    {
        return $this === self::Finalized;
    }
}
