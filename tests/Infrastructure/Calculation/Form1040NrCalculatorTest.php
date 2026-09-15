<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Calculation;

use App\Domain\Calculation\DTO\CalculationInput;
use App\Domain\Questionnaire\ValueObject\FormType;
use App\Infrastructure\Calculation\Form1040NrCalculator;
use PHPUnit\Framework\TestCase;

final class Form1040NrCalculatorTest extends TestCase
{
    public function testItSupportsOnlyThe1040NrFormType(): void
    {
        $calculator = new Form1040NrCalculator();

        self::assertTrue($calculator->supports(FormType::Form1040Nr));
        self::assertFalse($calculator->supports(FormType::FormW8Ben));
    }

    public function testItComputesTaxableIncomeTaxOwedAndBalanceFromAnswers(): void
    {
        $calculator = new Form1040NrCalculator();

        $result = $calculator->calculate(new CalculationInput(FormType::Form1040Nr, [
            'income_wages' => '50000',
            'treaty_exempt_amount' => '10000',
            'tax_withheld' => '3000',
        ]));

        self::assertSame(40000.0, $result->value(Form1040NrCalculator::FIELD_TOTAL_INCOME));
        self::assertSame(40000.0, $result->value(Form1040NrCalculator::FIELD_TOTAL_ECI));
        self::assertSame(40000.0, $result->value(Form1040NrCalculator::FIELD_ADJUSTED_GROSS_INCOME));
        self::assertSame(40000.0, $result->value(Form1040NrCalculator::FIELD_ADJUSTED_GROSS_INCOME_B));
        self::assertSame(10000.0, $result->value(Form1040NrCalculator::FIELD_TREATY_EXEMPTION));
        self::assertSame(40000.0, $result->value(Form1040NrCalculator::FIELD_TAXABLE_INCOME));
        self::assertSame(4000.0, $result->value(Form1040NrCalculator::FIELD_TAX_OWED));
        self::assertSame(4000.0, $result->value(Form1040NrCalculator::FIELD_TAX_SUBTOTAL));
        self::assertSame(4000.0, $result->value(Form1040NrCalculator::FIELD_TAX_AFTER_CREDITS));
        self::assertSame(4000.0, $result->value(Form1040NrCalculator::FIELD_TOTAL_TAX));
        self::assertSame(3000.0, $result->value(Form1040NrCalculator::FIELD_TAX_WITHHELD));
        self::assertSame(3000.0, $result->value(Form1040NrCalculator::FIELD_TOTAL_PAYMENTS));
        self::assertSame(1000.0, $result->value(Form1040NrCalculator::FIELD_AMOUNT_OWED));
        self::assertSame(0.0, $result->value(Form1040NrCalculator::FIELD_AMOUNT_OVERPAID));
        self::assertSame('X', $result->value(Form1040NrCalculator::FIELD_FILING_SINGLE));
        self::assertSame('', $result->value(Form1040NrCalculator::FIELD_FILING_MFS));
    }

    public function testMissingAmountsAreTreatedAsZeroAndOverpaymentIsReported(): void
    {
        $calculator = new Form1040NrCalculator();

        $result = $calculator->calculate(new CalculationInput(FormType::Form1040Nr, [
            'income_wages' => 20000,
            'tax_withheld' => 2500,
        ]));

        self::assertSame(20000.0, $result->value(Form1040NrCalculator::FIELD_TAXABLE_INCOME));
        self::assertSame(2000.0, $result->value(Form1040NrCalculator::FIELD_TAX_OWED));
        self::assertSame(0.0, $result->value(Form1040NrCalculator::FIELD_AMOUNT_OWED));
        self::assertSame(500.0, $result->value(Form1040NrCalculator::FIELD_AMOUNT_OVERPAID));
    }

    public function testTreatyExemptionCannotCreateNegativeTaxableIncome(): void
    {
        $calculator = new Form1040NrCalculator();

        $result = $calculator->calculate(new CalculationInput(FormType::Form1040Nr, [
            'income_wages' => '1000',
            'treaty_exempt_amount' => '5000',
        ]));

        self::assertSame(0.0, $result->value(Form1040NrCalculator::FIELD_TOTAL_INCOME));
        self::assertSame(0.0, $result->value(Form1040NrCalculator::FIELD_ADJUSTED_GROSS_INCOME));
        self::assertSame(0.0, $result->value(Form1040NrCalculator::FIELD_TAXABLE_INCOME));
        self::assertSame(0.0, $result->value(Form1040NrCalculator::FIELD_TAX_OWED));
        self::assertSame(0.0, $result->value(Form1040NrCalculator::FIELD_AMOUNT_OWED));
        self::assertSame(0.0, $result->value(Form1040NrCalculator::FIELD_AMOUNT_OVERPAID));
    }

    public function testMarriedAnswerMarksTheMfsCheckbox(): void
    {
        $calculator = new Form1040NrCalculator();

        $result = $calculator->calculate(new CalculationInput(FormType::Form1040Nr, [
            'married' => 'yes',
            'income_wages' => '1000',
        ]));

        self::assertSame('', $result->value(Form1040NrCalculator::FIELD_FILING_SINGLE));
        self::assertSame('X', $result->value(Form1040NrCalculator::FIELD_FILING_MFS));
    }
}
