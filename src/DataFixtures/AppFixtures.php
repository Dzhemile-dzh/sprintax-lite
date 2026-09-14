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
        $questionnaire->addQuestion(
            $personal->id(),
            $this->id(),
            'last_name',
            'Last name',
            QuestionType::ShortText,
            null,
            QuestionValidation::required(),
        );
        $questionnaire->addQuestion(
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
        $questionnaire->addQuestion(
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

        $questionnaire->addMapping(QuestionMapping::forQuestion(
            $this->id(),
            $firstName->key(),
            new PdfCoordinates(1, 20.5, 40.25, 11),
        ));
        $questionnaire->addMapping(QuestionMapping::forQuestion(
            $this->id(),
            $spouseName->key(),
            new PdfCoordinates(1, 20.5, 50.0, 11),
        ));
        $questionnaire->addMapping(QuestionMapping::forQuestion(
            $this->id(),
            $wages->key(),
            new PdfCoordinates(1, 100.0, 120.0, 10),
        ));
        $questionnaire->addMapping(QuestionMapping::forComputedField(
            $this->id(),
            Form1040NrCalculator::FIELD_TAX_OWED,
            new PdfCoordinates(1, 100.0, 180.5, 9),
        ));

        return $questionnaire;
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
