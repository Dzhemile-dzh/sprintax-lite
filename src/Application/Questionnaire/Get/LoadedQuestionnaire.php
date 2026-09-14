<?php

declare(strict_types=1);

namespace App\Application\Questionnaire\Get;

use App\Domain\Questionnaire\Entity\Questionnaire;

final readonly class LoadedQuestionnaire
{
    public function __construct(
        public Questionnaire $questionnaire,
        public bool $formTypeLocked,
    ) {
    }
}
