<?php

declare(strict_types=1);

namespace App\Domain\Analytics\DTO;

final readonly class QuestionnaireActivity
{
    public function __construct(
        public string $questionnaireId,
        public string $questionnaireName,
        public int $started,
        public int $inProgress,
        public int $finalized,
        public int $pdfReady,
        public int $pdfEmailed,
    ) {
    }
}
