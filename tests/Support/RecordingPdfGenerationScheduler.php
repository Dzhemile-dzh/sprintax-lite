<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Application\Pdf\PdfGenerationScheduler;

final class RecordingPdfGenerationScheduler implements PdfGenerationScheduler
{
    /**
     * @var list<string>
     */
    public array $scheduled = [];

    public function schedule(string $submissionId): void
    {
        $this->scheduled[] = $submissionId;
    }
}
