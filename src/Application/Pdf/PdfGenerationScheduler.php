<?php

declare(strict_types=1);

namespace App\Application\Pdf;

interface PdfGenerationScheduler
{
    public function schedule(string $submissionId): void;
}
