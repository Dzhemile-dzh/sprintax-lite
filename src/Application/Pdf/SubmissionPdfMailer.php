<?php

declare(strict_types=1);

namespace App\Application\Pdf;

interface SubmissionPdfMailer
{
    public function send(
        string $recipientEmail,
        string $questionnaireName,
        string $absolutePdfPath,
        string $downloadFileName,
    ): void;
}
