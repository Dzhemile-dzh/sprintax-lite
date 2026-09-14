<?php

declare(strict_types=1);

namespace App\Tests\Domain\Questionnaire;

use App\Domain\Questionnaire\Entity\QuestionMapping;
use App\Domain\Questionnaire\Entity\QuestionOption;
use App\Domain\Questionnaire\Entity\Questionnaire;
use App\Domain\Questionnaire\Exception\InvalidQuestionnaire;
use App\Domain\Questionnaire\ValueObject\PdfCoordinates;
use App\Domain\Questionnaire\ValueObject\QuestionType;
use App\Domain\Questionnaire\ValueObject\VisibilityCondition;
use App\Domain\Questionnaire\ValueObject\VisibilityOperator;
use App\Domain\Questionnaire\ValueObject\VisibilityRule;
use PHPUnit\Framework\TestCase;

final class QuestionnaireTest extends TestCase
{
    public function testStepsAndQuestionsAreOrderedByPosition(): void
    {
        $questionnaire = $this->questionnaireWithPersonalAndIncomeSteps();

        self::assertSame(['personal', 'income'], array_map(
            static fn ($step): string => $step->id(),
            $questionnaire->steps(),
        ));
        self::assertSame(['married', 'income_wages'], array_map(
            static fn ($question): string => $question->key(),
            $questionnaire->allQuestions(),
        ));
    }

    public function testDuplicateQuestionKeysAreRejected(): void
    {
        $questionnaire = Questionnaire::create('q-1', '1040-NR');
        $questionnaire->addStep('step-1', 'Personal');
        $questionnaire->addQuestion('step-1', 'question-1', 'married', 'Are you married?', QuestionType::YesNo);

        $this->expectException(InvalidQuestionnaire::class);
        $questionnaire->addQuestion('step-1', 'question-2', 'married', 'Married again?', QuestionType::YesNo);
    }

    public function testChoiceQuestionsAcceptOptionsAndTextQuestionsDoNot(): void
    {
        $questionnaire = Questionnaire::create('q-1', '1040-NR');
        $questionnaire->addStep('step-1', 'Personal');
        $status = $questionnaire->addQuestion(
            'step-1',
            'question-1',
            'status',
            'Status',
            QuestionType::SingleChoice,
        );
        $status->addOption(QuestionOption::create('opt-1', 'Single', 'single', 1));
        $status->addOption(QuestionOption::create('opt-2', 'Married', 'married', 2));

        self::assertCount(2, $status->options());

        $name = $questionnaire->addQuestion(
            'step-1',
            'question-2',
            'first_name',
            'First name',
            QuestionType::ShortText,
        );

        $this->expectException(InvalidQuestionnaire::class);
        $name->addOption(QuestionOption::create('opt-3', 'Nope', 'nope', 1));
    }

    public function testMappingMustReferenceAQuestionInTheQuestionnaire(): void
    {
        $questionnaire = Questionnaire::create('q-1', '1040-NR');
        $questionnaire->addStep('step-1', 'Personal');
        $questionnaire->addQuestion('step-1', 'question-1', 'married', 'Married?', QuestionType::YesNo);

        $questionnaire->addMapping(QuestionMapping::forQuestion(
            'map-1',
            'question-1',
            new PdfCoordinates(1, 20.0, 40.0, 10),
        ));
        $questionnaire->addMapping(QuestionMapping::forComputedField(
            'map-2',
            'tax_owed',
            new PdfCoordinates(2, 15.0, 80.5),
        ));

        self::assertCount(2, $questionnaire->mappings());

        $this->expectException(InvalidQuestionnaire::class);
        $questionnaire->addMapping(QuestionMapping::forQuestion(
            'map-3',
            'missing',
            new PdfCoordinates(1, 10.0, 10.0),
        ));
    }

    public function testDuplicateMappingSourcesAreRejected(): void
    {
        $questionnaire = Questionnaire::create('q-1', '1040-NR');
        $questionnaire->addStep('step-1', 'Personal');
        $questionnaire->addQuestion('step-1', 'question-1', 'married', 'Married?', QuestionType::YesNo);
        $questionnaire->addMapping(QuestionMapping::forQuestion(
            'map-1',
            'question-1',
            new PdfCoordinates(1, 20.0, 40.0, 10),
        ));

        $this->expectException(InvalidQuestionnaire::class);
        $questionnaire->addMapping(QuestionMapping::forQuestion(
            'map-2',
            'question-1',
            new PdfCoordinates(1, 10.0, 10.0),
        ));
    }

    public function testVisibilityRuleCanDependOnAnotherQuestion(): void
    {
        $questionnaire = Questionnaire::create('q-1', '1040-NR');
        $questionnaire->addStep('step-1', 'Personal');
        $questionnaire->addQuestion('step-1', 'question-1', 'married', 'Married?', QuestionType::YesNo);
        $spouseName = $questionnaire->addQuestion(
            'step-1',
            'question-2',
            'spouse_name',
            "Spouse's name",
            QuestionType::ShortText,
            visibility: new VisibilityRule([
                new VisibilityCondition('married', VisibilityOperator::Equals, 'yes'),
            ]),
        );

        self::assertFalse($spouseName->visibility()->isAlwaysVisible());
        self::assertSame('married', $spouseName->visibility()->conditions[0]->questionKey);
    }

    public function testNextAndPreviousStepsFollowPosition(): void
    {
        $questionnaire = $this->questionnaireWithPersonalAndIncomeSteps();

        self::assertSame('income', $questionnaire->nextStepAfter('personal')?->id());
        self::assertNull($questionnaire->nextStepAfter('income'));
        self::assertNull($questionnaire->previousStepBefore('personal'));
        self::assertSame('personal', $questionnaire->previousStepBefore('income')?->id());
    }

    private function questionnaireWithPersonalAndIncomeSteps(): Questionnaire
    {
        $questionnaire = Questionnaire::create('q-1', '1040-NR', 'Demo');
        $questionnaire->addStep('personal', 'Personal');
        $questionnaire->addStep('income', 'Income');
        $questionnaire->addQuestion('personal', 'q-married', 'married', 'Married?', QuestionType::YesNo);
        $questionnaire->addQuestion('income', 'q-wages', 'income_wages', 'Wages', QuestionType::Number);

        return $questionnaire;
    }
}
