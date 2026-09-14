<?php

declare(strict_types=1);

namespace App\Tests\Domain\Questionnaire;

use App\Domain\Questionnaire\Entity\QuestionOption;
use App\Domain\Questionnaire\Entity\Questionnaire;
use App\Domain\Questionnaire\QuestionVisibilityEvaluator;
use App\Domain\Questionnaire\ValueObject\FormType;
use App\Domain\Questionnaire\ValueObject\QuestionType;
use App\Domain\Questionnaire\ValueObject\VisibilityCondition;
use App\Domain\Questionnaire\ValueObject\VisibilityOperator;
use App\Domain\Questionnaire\ValueObject\VisibilityRule;
use App\Domain\Submission\ValueObject\AnswerValue;
use PHPUnit\Framework\TestCase;

final class QuestionVisibilityEvaluatorTest extends TestCase
{
    public function testQuestionsWithoutConditionsAreAlwaysVisible(): void
    {
        $questionnaire = $this->personalQuestionnaire();
        $married = $questionnaire->findQuestionByKey('married');
        self::assertNotNull($married);

        $evaluator = new QuestionVisibilityEvaluator();

        self::assertTrue($evaluator->isVisible($married, $questionnaire, []));
    }

    public function testEqualsShowsTheQuestionOnlyWhenTheAnswerMatches(): void
    {
        $questionnaire = $this->personalQuestionnaire();
        $spouseName = $questionnaire->findQuestionByKey('spouse_name');
        self::assertNotNull($spouseName);

        $evaluator = new QuestionVisibilityEvaluator();

        self::assertFalse($evaluator->isVisible($spouseName, $questionnaire, []));
        self::assertFalse($evaluator->isVisible($spouseName, $questionnaire, [
            'married' => AnswerValue::text('no'),
        ]));
        self::assertTrue($evaluator->isVisible($spouseName, $questionnaire, [
            'married' => AnswerValue::text('yes'),
        ]));
    }

    public function testNotEqualsRequiresAnAnswerThatDiffers(): void
    {
        $questionnaire = Questionnaire::create('q-1', '1040-NR', FormType::Form1040Nr);
        $questionnaire->addStep('step-1', 'Personal');
        $questionnaire->addQuestion('step-1', 'q-married', 'married', 'Married?', QuestionType::YesNo);
        $singleNote = $questionnaire->addQuestion(
            'step-1',
            'q-single',
            'single_note',
            'Single filer note',
            QuestionType::ShortText,
            visibility: new VisibilityRule([
                new VisibilityCondition('married', VisibilityOperator::NotEquals, 'yes'),
            ]),
        );

        $evaluator = new QuestionVisibilityEvaluator();

        self::assertFalse($evaluator->isVisible($singleNote, $questionnaire, []));
        self::assertFalse($evaluator->isVisible($singleNote, $questionnaire, [
            'married' => AnswerValue::text('yes'),
        ]));
        self::assertTrue($evaluator->isVisible($singleNote, $questionnaire, [
            'married' => AnswerValue::text('no'),
        ]));
    }

    public function testMultiChoiceEqualsMatchesWhenTheExpectedValueIsSelected(): void
    {
        $questionnaire = Questionnaire::create('q-1', '1040-NR', FormType::Form1040Nr);
        $questionnaire->addStep('step-1', 'Income');
        $incomeTypes = $questionnaire->addQuestion(
            'step-1',
            'q-types',
            'income_types',
            'Income types',
            QuestionType::MultiChoice,
        );
        $incomeTypes->addOption(QuestionOption::create('opt-1', 'Wages', 'wages', 1));
        $incomeTypes->addOption(QuestionOption::create('opt-2', 'Scholarship', 'scholarship', 2));
        $wages = $questionnaire->addQuestion(
            'step-1',
            'q-wages',
            'income_wages',
            'Wages',
            QuestionType::Number,
            visibility: new VisibilityRule([
                new VisibilityCondition('income_types', VisibilityOperator::Equals, 'wages'),
            ]),
        );

        $evaluator = new QuestionVisibilityEvaluator();

        self::assertFalse($evaluator->isVisible($wages, $questionnaire, [
            'income_types' => AnswerValue::choices(['scholarship']),
        ]));
        self::assertTrue($evaluator->isVisible($wages, $questionnaire, [
            'income_types' => AnswerValue::choices(['scholarship', 'wages']),
        ]));
    }

    public function testMultiChoiceNotEqualsMeansTheValueIsNotSelected(): void
    {
        $questionnaire = Questionnaire::create('q-1', '1040-NR', FormType::Form1040Nr);
        $questionnaire->addStep('step-1', 'Income');
        $incomeTypes = $questionnaire->addQuestion(
            'step-1',
            'q-types',
            'income_types',
            'Income types',
            QuestionType::MultiChoice,
        );
        $incomeTypes->addOption(QuestionOption::create('opt-1', 'Wages', 'wages', 1));
        $incomeTypes->addOption(QuestionOption::create('opt-2', 'Scholarship', 'scholarship', 2));
        $noWagesNote = $questionnaire->addQuestion(
            'step-1',
            'q-no-wages',
            'no_wages_note',
            'No wages note',
            QuestionType::ShortText,
            visibility: new VisibilityRule([
                new VisibilityCondition('income_types', VisibilityOperator::NotEquals, 'wages'),
            ]),
        );

        $evaluator = new QuestionVisibilityEvaluator();

        self::assertTrue($evaluator->isVisible($noWagesNote, $questionnaire, [
            'income_types' => AnswerValue::choices(['scholarship']),
        ]));
        self::assertTrue($evaluator->isVisible($noWagesNote, $questionnaire, [
            'income_types' => AnswerValue::choices([]),
        ]));
        self::assertFalse($evaluator->isVisible($noWagesNote, $questionnaire, [
            'income_types' => AnswerValue::choices(['scholarship', 'wages']),
        ]));
    }

    public function testAllConditionsMustHold(): void
    {
        $questionnaire = Questionnaire::create('q-1', '1040-NR', FormType::Form1040Nr);
        $questionnaire->addStep('step-1', 'Personal');
        $questionnaire->addQuestion('step-1', 'q-married', 'married', 'Married?', QuestionType::YesNo);
        $questionnaire->addQuestion('step-1', 'q-us', 'spouse_in_us', 'Spouse in the US?', QuestionType::YesNo);
        $ssn = $questionnaire->addQuestion(
            'step-1',
            'q-ssn',
            'spouse_ssn',
            'Spouse SSN',
            QuestionType::ShortText,
            visibility: new VisibilityRule([
                new VisibilityCondition('married', VisibilityOperator::Equals, 'yes'),
                new VisibilityCondition('spouse_in_us', VisibilityOperator::Equals, 'yes'),
            ]),
        );

        $evaluator = new QuestionVisibilityEvaluator();

        self::assertFalse($evaluator->isVisible($ssn, $questionnaire, [
            'married' => AnswerValue::text('yes'),
            'spouse_in_us' => AnswerValue::text('no'),
        ]));
        self::assertTrue($evaluator->isVisible($ssn, $questionnaire, [
            'married' => AnswerValue::text('yes'),
            'spouse_in_us' => AnswerValue::text('yes'),
        ]));
    }

    public function testADependentStaysHiddenWhenItsControllerIsHidden(): void
    {
        $questionnaire = Questionnaire::create('q-1', '1040-NR', FormType::Form1040Nr);
        $questionnaire->addStep('step-1', 'Personal');
        $questionnaire->addQuestion('step-1', 'q-married', 'married', 'Married?', QuestionType::YesNo);
        $questionnaire->addQuestion(
            'step-1',
            'q-spouse',
            'spouse_name',
            "Spouse's name",
            QuestionType::ShortText,
            visibility: new VisibilityRule([
                new VisibilityCondition('married', VisibilityOperator::Equals, 'yes'),
            ]),
        );
        $itin = $questionnaire->addQuestion(
            'step-1',
            'q-itin',
            'spouse_itin',
            'Spouse ITIN',
            QuestionType::ShortText,
            visibility: new VisibilityRule([
                new VisibilityCondition('spouse_name', VisibilityOperator::Equals, 'Alex'),
            ]),
        );

        $evaluator = new QuestionVisibilityEvaluator();

        self::assertFalse($evaluator->isVisible($itin, $questionnaire, [
            'married' => AnswerValue::text('no'),
            'spouse_name' => AnswerValue::text('Alex'),
        ]));
    }

    public function testVisibleQuestionsOnAStepExcludeHiddenOnes(): void
    {
        $questionnaire = $this->personalQuestionnaire();
        $step = $questionnaire->steps()[0];
        $evaluator = new QuestionVisibilityEvaluator();

        $visibleWithoutMarriage = $evaluator->visibleQuestionsOnStep($step, $questionnaire, []);
        self::assertSame(['married'], array_map(
            static fn ($question): string => $question->key(),
            $visibleWithoutMarriage,
        ));

        $visibleWhenMarried = $evaluator->visibleQuestionsOnStep($step, $questionnaire, [
            'married' => AnswerValue::text('yes'),
        ]);
        self::assertSame(['married', 'spouse_name'], array_map(
            static fn ($question): string => $question->key(),
            $visibleWhenMarried,
        ));
    }

    public function testAMissingControllerQuestionHidesTheDependent(): void
    {
        $questionnaire = Questionnaire::create('q-1', '1040-NR', FormType::Form1040Nr);
        $questionnaire->addStep('step-1', 'Personal');
        $orphan = $questionnaire->addQuestion(
            'step-1',
            'q-orphan',
            'orphan',
            'Orphan',
            QuestionType::ShortText,
            visibility: new VisibilityRule([
                new VisibilityCondition('missing_key', VisibilityOperator::Equals, 'yes'),
            ]),
        );

        $evaluator = new QuestionVisibilityEvaluator();

        self::assertFalse($evaluator->isVisible($orphan, $questionnaire, [
            'missing_key' => AnswerValue::text('yes'),
        ]));
    }

    public function testBlankTextIsTreatedAsUnanswered(): void
    {
        $questionnaire = Questionnaire::create('q-1', '1040-NR', FormType::Form1040Nr);
        $questionnaire->addStep('step-1', 'Personal');
        $questionnaire->addQuestion('step-1', 'q-married', 'married', 'Married?', QuestionType::YesNo);
        $singleNote = $questionnaire->addQuestion(
            'step-1',
            'q-single',
            'single_note',
            'Single filer note',
            QuestionType::ShortText,
            visibility: new VisibilityRule([
                new VisibilityCondition('married', VisibilityOperator::NotEquals, 'yes'),
            ]),
        );

        $evaluator = new QuestionVisibilityEvaluator();

        self::assertFalse($evaluator->isVisible($singleNote, $questionnaire, [
            'married' => AnswerValue::text(''),
        ]));
        self::assertFalse($evaluator->isVisible($singleNote, $questionnaire, [
            'married' => AnswerValue::text('   '),
        ]));
    }

    public function testCyclicRulesHideBothQuestions(): void
    {
        $questionnaire = Questionnaire::create('q-1', '1040-NR', FormType::Form1040Nr);
        $questionnaire->addStep('step-1', 'Personal');
        $first = $questionnaire->addQuestion(
            'step-1',
            'q-a',
            'alpha',
            'Alpha',
            QuestionType::YesNo,
            visibility: new VisibilityRule([
                new VisibilityCondition('beta', VisibilityOperator::Equals, 'yes'),
            ]),
        );
        $second = $questionnaire->addQuestion(
            'step-1',
            'q-b',
            'beta',
            'Beta',
            QuestionType::YesNo,
            visibility: new VisibilityRule([
                new VisibilityCondition('alpha', VisibilityOperator::Equals, 'yes'),
            ]),
        );

        $evaluator = new QuestionVisibilityEvaluator();
        $answers = [
            'alpha' => AnswerValue::text('yes'),
            'beta' => AnswerValue::text('yes'),
        ];

        self::assertFalse($evaluator->isVisible($first, $questionnaire, $answers));
        self::assertFalse($evaluator->isVisible($second, $questionnaire, $answers));
    }

    public function testADependentOnALaterStepUsesTheEarlierAnswer(): void
    {
        $questionnaire = Questionnaire::create('q-1', '1040-NR', FormType::Form1040Nr);
        $questionnaire->addStep('step-1', 'Personal');
        $questionnaire->addStep('step-2', 'Spouse');
        $questionnaire->addQuestion('step-1', 'q-married', 'married', 'Married?', QuestionType::YesNo);
        $spouseName = $questionnaire->addQuestion(
            'step-2',
            'q-spouse',
            'spouse_name',
            "Spouse's name",
            QuestionType::ShortText,
            visibility: new VisibilityRule([
                new VisibilityCondition('married', VisibilityOperator::Equals, 'yes'),
            ]),
        );

        $evaluator = new QuestionVisibilityEvaluator();
        $spouseStep = $questionnaire->steps()[1];

        self::assertSame([], $evaluator->visibleQuestionsOnStep($spouseStep, $questionnaire, [
            'married' => AnswerValue::text('no'),
        ]));
        self::assertSame([$spouseName], $evaluator->visibleQuestionsOnStep($spouseStep, $questionnaire, [
            'married' => AnswerValue::text('yes'),
        ]));
    }

    private function personalQuestionnaire(): Questionnaire
    {
        $questionnaire = Questionnaire::create('q-1', '1040-NR', FormType::Form1040Nr);
        $questionnaire->addStep('step-1', 'Personal');
        $questionnaire->addQuestion('step-1', 'q-married', 'married', 'Married?', QuestionType::YesNo);
        $questionnaire->addQuestion(
            'step-1',
            'q-spouse',
            'spouse_name',
            "Spouse's name",
            QuestionType::ShortText,
            visibility: new VisibilityRule([
                new VisibilityCondition('married', VisibilityOperator::Equals, 'yes'),
            ]),
        );

        return $questionnaire;
    }
}
