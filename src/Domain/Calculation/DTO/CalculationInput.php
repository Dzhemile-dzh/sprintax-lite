<?php

declare(strict_types=1);

namespace App\Domain\Calculation\DTO;

use App\Domain\Questionnaire\ValueObject\FormType;

final readonly class CalculationInput
{
    /**
     * @param array<string, mixed> $answers
     */
    public function __construct(
        public FormType $formType,
        public array $answers,
    ) {
    }
}
