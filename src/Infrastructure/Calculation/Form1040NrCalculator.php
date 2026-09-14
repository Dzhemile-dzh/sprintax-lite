<?php

declare(strict_types=1);

namespace App\Infrastructure\Calculation;

use App\Domain\Calculation\Contract\CalculatorInterface;
use App\Domain\Calculation\DTO\CalculationInput;
use App\Domain\Calculation\DTO\CalculationResult;
use App\Domain\Questionnaire\ValueObject\FormType;

/**
 * Simplified 1040-NR stand-in: 10% of taxable income after a treaty exemption.
 * Real IRS tables are intentionally out of scope.
 */
final class Form1040NrCalculator implements CalculatorInterface
{
    public const FIELD_TOTAL_INCOME = 'total_income';
    public const FIELD_TREATY_EXEMPTION = 'treaty_exemption';
    public const FIELD_TAXABLE_INCOME = 'taxable_income';
    public const FIELD_TAX_OWED = 'tax_owed';
    public const FIELD_TAX_WITHHELD = 'tax_withheld';
    public const FIELD_AMOUNT_OWED = 'amount_owed';
    public const FIELD_AMOUNT_OVERPAID = 'amount_overpaid';

    private const RATE = 0.10;

    public function supports(FormType $formType): bool
    {
        return $formType === FormType::Form1040Nr;
    }

    public function calculate(CalculationInput $input): CalculationResult
    {
        $wages = $this->number($input->answers, 'income_wages');
        $treaty = $this->number($input->answers, 'treaty_exempt_amount');
        $withheld = $this->number($input->answers, 'tax_withheld');

        $taxableIncome = max(0.0, $wages - $treaty);
        $taxOwed = round($taxableIncome * self::RATE, 2);
        $amountOwed = round(max(0.0, $taxOwed - $withheld), 2);
        $amountOverpaid = round(max(0.0, $withheld - $taxOwed), 2);

        return new CalculationResult([
            self::FIELD_TOTAL_INCOME => $wages,
            self::FIELD_TREATY_EXEMPTION => $treaty,
            self::FIELD_TAXABLE_INCOME => $taxableIncome,
            self::FIELD_TAX_OWED => $taxOwed,
            self::FIELD_TAX_WITHHELD => $withheld,
            self::FIELD_AMOUNT_OWED => $amountOwed,
            self::FIELD_AMOUNT_OVERPAID => $amountOverpaid,
        ]);
    }

    /**
     * @param array<string, mixed> $answers
     */
    private function number(array $answers, string $key): float
    {
        if (!array_key_exists($key, $answers)) {
            return 0.0;
        }

        $raw = $answers[$key];

        if (is_int($raw) || is_float($raw)) {
            return (float) $raw;
        }

        if (is_string($raw) && is_numeric($raw)) {
            return (float) $raw;
        }

        return 0.0;
    }
}
