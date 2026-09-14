<?php

declare(strict_types=1);

namespace App\Domain\Questionnaire\ValueObject;

use App\Domain\Questionnaire\Exception\InvalidQuestionnaire;

final readonly class PdfCoordinates
{
    public function __construct(
        public int $page,
        public float $xMm,
        public float $yMm,
        public ?int $fontSize = null,
    ) {
        if ($this->page < 1) {
            throw InvalidQuestionnaire::invalidPdfCoordinates('page must be at least 1');
        }

        if ($this->fontSize !== null && $this->fontSize < 1) {
            throw InvalidQuestionnaire::invalidPdfCoordinates('font size must be at least 1');
        }
    }
}
