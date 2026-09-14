<?php

declare(strict_types=1);

namespace App\Domain\Calculation\Contract;

use App\Domain\Calculation\DTO\CalculationInput;
use App\Domain\Calculation\DTO\CalculationResult;
use App\Domain\Questionnaire\ValueObject\FormType;

interface CalculatorInterface
{
    public function supports(FormType $formType): bool;

    /**
     * @return list<string>
     */
    public function outputFields(): array;

    public function calculate(CalculationInput $input): CalculationResult;
}
