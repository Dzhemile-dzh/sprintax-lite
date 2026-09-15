<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Application\User\PasswordHasherInterface;
use App\Domain\Questionnaire\Entity\Question;
use App\Domain\Questionnaire\Entity\QuestionMapping;
use App\Domain\Questionnaire\Entity\Questionnaire;
use App\Domain\Questionnaire\Entity\QuestionOption;
use App\Domain\Questionnaire\ValueObject\FormType;
use App\Domain\Questionnaire\ValueObject\PdfCoordinates;
use App\Domain\Questionnaire\ValueObject\QuestionType;
use App\Domain\Questionnaire\ValueObject\QuestionValidation;
use App\Domain\Questionnaire\ValueObject\VisibilityCondition;
use App\Domain\Questionnaire\ValueObject\VisibilityOperator;
use App\Domain\Questionnaire\ValueObject\VisibilityRule;
use App\Domain\User\Entity\User;
use App\Domain\User\ValueObject\Email;
use App\Infrastructure\Calculation\Form1040NrCalculator;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

final class AppFixtures extends Fixture
{
    public const ADMIN_EMAIL = 'admin@example.test';
    public const ADMIN_PASSWORD = 'admin123';
    public const CLIENT_EMAIL = 'client@example.test';
    public const CLIENT_PASSWORD = 'client123';

    public function __construct(
        private readonly PasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $manager->persist(User::provisionAdmin(
            $this->id(),
            new Email(self::ADMIN_EMAIL),
            $this->passwordHasher->hash(self::ADMIN_PASSWORD),
        ));
        $manager->persist(User::registerClient(
            $this->id(),
            new Email(self::CLIENT_EMAIL),
            $this->passwordHasher->hash(self::CLIENT_PASSWORD),
        ));
        $manager->persist($this->demoQuestionnaire());
        $manager->flush();
    }

    private function demoQuestionnaire(): Questionnaire
    {
        $questionnaire = Questionnaire::create(
            $this->id(),
            '1040-NR',
            FormType::Form1040Nr,
            'Demo Form 1040-NR with conditional spouse questions and computed tax fields.',
        );

        $personal = $questionnaire->addStep($this->id(), 'Personal');
        $income = $questionnaire->addStep($this->id(), 'Income');

        $firstName = $questionnaire->addQuestion(
            $personal->id(),
            $this->id(),
            'first_name',
            'First name',
            QuestionType::ShortText,
            'As shown on your passport.',
            QuestionValidation::required(),
        );
        $lastName = $questionnaire->addQuestion(
            $personal->id(),
            $this->id(),
            'last_name',
            'Last name',
            QuestionType::ShortText,
            null,
            QuestionValidation::required(),
        );
        $birthDate = $questionnaire->addQuestion(
            $personal->id(),
            $this->id(),
            'birth_date',
            'Date of birth',
            QuestionType::Date,
        );
        $questionnaire->addQuestion(
            $personal->id(),
            $this->id(),
            'married',
            'Married?',
            QuestionType::YesNo,
            null,
            QuestionValidation::required(),
        );
        $spouseName = $questionnaire->addQuestion(
            $personal->id(),
            $this->id(),
            'spouse_name',
            'Spouse name',
            QuestionType::ShortText,
            'Required only if you are married.',
            QuestionValidation::required(),
            new VisibilityRule([
                new VisibilityCondition('married', VisibilityOperator::Equals, 'yes'),
            ]),
        );

        $residency = $questionnaire->addQuestion(
            $income->id(),
            $this->id(),
            'residency',
            'Residency status',
            QuestionType::SingleChoice,
            null,
            QuestionValidation::required(),
        );
        $this->addOption($residency, 'Resident', 'resident', 1);
        $this->addOption($residency, 'Nonresident', 'nonresident', 2);

        $incomeTypes = $questionnaire->addQuestion(
            $income->id(),
            $this->id(),
            'income_types',
            'Income types',
            QuestionType::MultiChoice,
        );
        $this->addOption($incomeTypes, 'Wages', 'wages', 1);
        $this->addOption($incomeTypes, 'Treaty-exempt income', 'treaty', 2);

        $wages = $questionnaire->addQuestion(
            $income->id(),
            $this->id(),
            'income_wages',
            'Wages',
            QuestionType::Number,
            'US-source wages for the tax year.',
            QuestionValidation::required(),
            new VisibilityRule([
                new VisibilityCondition('income_types', VisibilityOperator::Equals, 'wages'),
            ]),
        );
        $treatyExempt = $questionnaire->addQuestion(
            $income->id(),
            $this->id(),
            'treaty_exempt_amount',
            'Treaty exemption',
            QuestionType::Number,
            'Amount excluded under a tax treaty.',
            null,
            new VisibilityRule([
                new VisibilityCondition('income_types', VisibilityOperator::Equals, 'treaty'),
            ]),
        );
        $questionnaire->addQuestion(
            $income->id(),
            $this->id(),
            'tax_withheld',
            'Tax withheld',
            QuestionType::Number,
        );

        // Coordinates are millimetres from the top-left of 2025 Form 1040-NR (Letter).
        // Amounts share the IRS amount-column x; y matches each line-number baseline.
        $amountX = 188.0;
        $this->mapQuestion($questionnaire, $firstName->key(), 1, 14.0, 43.0);
        $this->mapQuestion($questionnaire, $lastName->key(), 1, 90.0, 43.0);
        $this->mapQuestion($questionnaire, $birthDate->key(), 1, 168.0, 43.0, 8);
        $this->mapComputed($questionnaire, Form1040NrCalculator::FIELD_FILING_SINGLE, 1, 36.5, 74.2, 10);
        $this->mapComputed($questionnaire, Form1040NrCalculator::FIELD_FILING_MFS, 1, 55.5, 74.2, 10);
        $this->mapQuestion($questionnaire, $spouseName->key(), 1, 38.0, 82.0, 8);
        $this->mapQuestion($questionnaire, $wages->key(), 1, $amountX, 143.1);
        $this->mapQuestion($questionnaire, $treatyExempt->key(), 1, $amountX, 189.7);
        $this->mapComputed($questionnaire, Form1040NrCalculator::FIELD_TOTAL_INCOME, 1, $amountX, 193.9);
        $this->mapComputed($questionnaire, Form1040NrCalculator::FIELD_TOTAL_ECI, 1, $amountX, 244.7);
        $this->mapComputed($questionnaire, Form1040NrCalculator::FIELD_ADJUSTED_GROSS_INCOME, 1, $amountX, 257.2);
        $this->mapComputed($questionnaire, Form1040NrCalculator::FIELD_ADJUSTED_GROSS_INCOME_B, 2, $amountX, 20.4);
        $this->mapComputed($questionnaire, Form1040NrCalculator::FIELD_TAXABLE_INCOME, 2, $amountX, 47.9);
        $this->mapComputed($questionnaire, Form1040NrCalculator::FIELD_TAX_OWED, 2, $amountX, 52.1);
        $this->mapComputed($questionnaire, Form1040NrCalculator::FIELD_TAX_SUBTOTAL, 2, $amountX, 60.6);
        $this->mapComputed($questionnaire, Form1040NrCalculator::FIELD_TAX_AFTER_CREDITS, 2, $amountX, 77.5);
        $this->mapComputed($questionnaire, Form1040NrCalculator::FIELD_TOTAL_TAX, 2, $amountX, 102.9);
        $this->mapComputed($questionnaire, Form1040NrCalculator::FIELD_TAX_WITHHELD, 2, $amountX, 124.1);
        $this->mapComputed($questionnaire, Form1040NrCalculator::FIELD_TOTAL_PAYMENTS, 2, $amountX, 172.8);
        $this->mapComputed($questionnaire, Form1040NrCalculator::FIELD_AMOUNT_OVERPAID, 2, $amountX, 177.0);
        $this->mapComputed($questionnaire, Form1040NrCalculator::FIELD_AMOUNT_OWED, 2, $amountX, 208.7);

        return $questionnaire;
    }

    private function mapQuestion(
        Questionnaire $questionnaire,
        string $key,
        int $page,
        float $xMm,
        float $yMm,
        int $fontSize = 9,
    ): void {
        $questionnaire->addMapping(QuestionMapping::forQuestion(
            $this->id(),
            $key,
            new PdfCoordinates($page, $xMm, $yMm, $fontSize),
        ));
    }

    private function mapComputed(
        Questionnaire $questionnaire,
        string $field,
        int $page,
        float $xMm,
        float $yMm,
        int $fontSize = 9,
    ): void {
        $questionnaire->addMapping(QuestionMapping::forComputedField(
            $this->id(),
            $field,
            new PdfCoordinates($page, $xMm, $yMm, $fontSize),
        ));
    }

    private function addOption(Question $question, string $label, string $value, int $position): void
    {
        $question->addOption(QuestionOption::create($this->id(), $label, $value, $position));
    }

    private function id(): string
    {
        return bin2hex(random_bytes(16));
    }
}
