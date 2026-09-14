<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Application\Pdf\SubmissionPdfMailer;
use RuntimeException;

final class FailingSubmissionPdfMailer implements SubmissionPdfMailer
{
    public function send(
        string $recipientEmail,
        string $questionnaireName,
        string $absolutePdfPath,
        string $downloadFileName,
    ): void {
        throw new RuntimeException('Mail transport failed.');
    }
}
