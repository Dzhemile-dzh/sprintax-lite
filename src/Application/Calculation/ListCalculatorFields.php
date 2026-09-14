<?php

declare(strict_types=1);

namespace App\Application\Calculation;

use App\Domain\Calculation\Contract\CalculatorInterface;
use App\Domain\Questionnaire\ValueObject\FormType;

final class ListCalculatorFields
{
    /**
     * @param iterable<CalculatorInterface> $calculators
     */
    public function __construct(
        private readonly iterable $calculators,
    ) {
    }

    /**
     * @return list<string>
     */
    public function execute(FormType $formType): array
    {
        foreach ($this->calculators as $calculator) {
            if ($calculator->supports($formType)) {
                return $calculator->outputFields();
            }
        }

        return [];
    }
}
