<?php

declare(strict_types=1);

namespace App\Infrastructure\Calculation;

use App\Domain\Calculation\Contract\CalculatorInterface;
use App\Domain\Calculation\DTO\CalculationInput;
use App\Domain\Calculation\DTO\CalculationResult;
use App\Domain\Questionnaire\ValueObject\FormType;

/**
 * Simplified 1040-NR stand-in: 10% of net income after a treaty exemption.
 * Wages stay on the wages line; total income, ECI, AGI, and taxable income all
 * use max(0, wages − treaty) so printed totals foot. Real IRS tables are out of scope.
 */
final class Form1040NrCalculator implements CalculatorInterface
{
    public const FIELD_TOTAL_INCOME = 'total_income';
    public const FIELD_TOTAL_ECI = 'total_eci';
    public const FIELD_TREATY_EXEMPTION = 'treaty_exemption';
    public const FIELD_ADJUSTED_GROSS_INCOME = 'adjusted_gross_income';
    public const FIELD_ADJUSTED_GROSS_INCOME_B = 'adjusted_gross_income_b';
    public const FIELD_TAXABLE_INCOME = 'taxable_income';
    public const FIELD_TAX_OWED = 'tax_owed';
    public const FIELD_TAX_SUBTOTAL = 'tax_subtotal';
    public const FIELD_TAX_AFTER_CREDITS = 'tax_after_credits';
    public const FIELD_TOTAL_TAX = 'total_tax';
    public const FIELD_TAX_WITHHELD = 'tax_withheld';
    public const FIELD_TOTAL_PAYMENTS = 'total_payments';
    public const FIELD_AMOUNT_OWED = 'amount_owed';
    public const FIELD_AMOUNT_OVERPAID = 'amount_overpaid';
    public const FIELD_FILING_SINGLE = 'filing_single';
    public const FIELD_FILING_MFS = 'filing_mfs';

    private const RATE = 0.10;

    public function supports(FormType $formType): bool
    {
        return $formType === FormType::Form1040Nr;
    }

    public function outputFields(): array
    {
        return [
            self::FIELD_TOTAL_INCOME,
            self::FIELD_TOTAL_ECI,
            self::FIELD_TREATY_EXEMPTION,
            self::FIELD_ADJUSTED_GROSS_INCOME,
            self::FIELD_ADJUSTED_GROSS_INCOME_B,
            self::FIELD_TAXABLE_INCOME,
            self::FIELD_TAX_OWED,
            self::FIELD_TAX_SUBTOTAL,
            self::FIELD_TAX_AFTER_CREDITS,
            self::FIELD_TOTAL_TAX,
            self::FIELD_TAX_WITHHELD,
            self::FIELD_TOTAL_PAYMENTS,
            self::FIELD_AMOUNT_OWED,
            self::FIELD_AMOUNT_OVERPAID,
            self::FIELD_FILING_SINGLE,
            self::FIELD_FILING_MFS,
        ];
    }

    public function calculate(CalculationInput $input): CalculationResult
    {
        $wages = $this->number($input->answers, 'income_wages');
        $treaty = $this->number($input->answers, 'treaty_exempt_amount');
        $withheld = $this->number($input->answers, 'tax_withheld');
        $married = $this->text($input->answers, 'married');

        $netIncome = max(0.0, $wages - $treaty);
        $taxOwed = round($netIncome * self::RATE, 2);
        $amountOwed = round(max(0.0, $taxOwed - $withheld), 2);
        $amountOverpaid = round(max(0.0, $withheld - $taxOwed), 2);
        $isMarried = $married === 'yes';

        return new CalculationResult([
            self::FIELD_TOTAL_INCOME => $netIncome,
            self::FIELD_TOTAL_ECI => $netIncome,
            self::FIELD_TREATY_EXEMPTION => $treaty,
            self::FIELD_ADJUSTED_GROSS_INCOME => $netIncome,
            self::FIELD_ADJUSTED_GROSS_INCOME_B => $netIncome,
            self::FIELD_TAXABLE_INCOME => $netIncome,
            self::FIELD_TAX_OWED => $taxOwed,
            self::FIELD_TAX_SUBTOTAL => $taxOwed,
            self::FIELD_TAX_AFTER_CREDITS => $taxOwed,
            self::FIELD_TOTAL_TAX => $taxOwed,
            self::FIELD_TAX_WITHHELD => $withheld,
            self::FIELD_TOTAL_PAYMENTS => $withheld,
            self::FIELD_AMOUNT_OWED => $amountOwed > 0.0 ? $amountOwed : '',
            self::FIELD_AMOUNT_OVERPAID => $amountOverpaid > 0.0 ? $amountOverpaid : '',
            self::FIELD_FILING_SINGLE => $isMarried ? '' : 'X',
            self::FIELD_FILING_MFS => $isMarried ? 'X' : '',
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

    /**
     * @param array<string, mixed> $answers
     */
    private function text(array $answers, string $key): string
    {
        if (!array_key_exists($key, $answers) || !is_string($answers[$key])) {
            return '';
        }

        return strtolower(trim($answers[$key]));
    }
}
