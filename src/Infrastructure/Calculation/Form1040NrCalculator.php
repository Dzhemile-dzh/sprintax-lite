<?php

declare(strict_types=1);

namespace App\Infrastructure\Calculation;

use App\Domain\Calculation\Contract\CalculatorInterface;
use App\Domain\Calculation\DTO\CalculationInput;
use App\Domain\Calculation\DTO\CalculationResult;
use LogicException;

final class Form1040NrCalculator implements CalculatorInterface
{
    public function supports(string $formType): bool
    {
        return $formType === '1040-nr';
    }

    public function calculate(CalculationInput $input): CalculationResult
    {
        throw new LogicException(sprintf(
            'The calculation engine is not implemented yet for "%s".',
            $input->formType,
        ));
    }
}
