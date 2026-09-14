<?php

declare(strict_types=1);

namespace App\Domain\Calculation\Contract;

use App\Domain\Calculation\DTO\CalculationInput;
use App\Domain\Calculation\DTO\CalculationResult;

interface CalculatorInterface
{
    public function supports(string $formType): bool;

    public function calculate(CalculationInput $input): CalculationResult;
}
