<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Application\Pdf\SubmissionPdfMailer;

final class RecordingSubmissionPdfMailer implements SubmissionPdfMailer
{
    /**
     * @var list<array{
     *     recipientEmail: string,
     *     questionnaireName: string,
     *     absolutePdfPath: string,
     *     downloadFileName: string
     * }>
     */
    public array $sent = [];

    public function send(
        string $recipientEmail,
        string $questionnaireName,
        string $absolutePdfPath,
        string $downloadFileName,
    ): void {
        $this->sent[] = [
            'recipientEmail' => $recipientEmail,
            'questionnaireName' => $questionnaireName,
            'absolutePdfPath' => $absolutePdfPath,
            'downloadFileName' => $downloadFileName,
        ];
    }
}
