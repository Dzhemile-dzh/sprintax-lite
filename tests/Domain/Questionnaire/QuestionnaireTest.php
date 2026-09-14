<?php

declare(strict_types=1);

namespace App\Tests\Domain\Questionnaire;

use App\Domain\Questionnaire\Entity\QuestionMapping;
use App\Domain\Questionnaire\Entity\QuestionOption;
use App\Domain\Questionnaire\Entity\Questionnaire;
use App\Domain\Questionnaire\Exception\InvalidQuestionnaire;
use App\Domain\Questionnaire\ValueObject\FormType;
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
        $questionnaire = Questionnaire::create('q-1', '1040-NR', FormType::Form1040Nr);
        $questionnaire->addStep('step-1', 'Personal');
        $questionnaire->addQuestion('step-1', 'question-1', 'married', 'Are you married?', QuestionType::YesNo);

        $this->expectException(InvalidQuestionnaire::class);
        $questionnaire->addQuestion('step-1', 'question-2', 'married', 'Married again?', QuestionType::YesNo);
    }

    public function testChoiceQuestionsAcceptOptionsAndTextQuestionsDoNot(): void
    {
        $questionnaire = Questionnaire::create('q-1', '1040-NR', FormType::Form1040Nr);
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
        $questionnaire = Questionnaire::create('q-1', '1040-NR', FormType::Form1040Nr);
        $questionnaire->addStep('step-1', 'Personal');
        $questionnaire->addQuestion('step-1', 'question-1', 'married', 'Married?', QuestionType::YesNo);

        $questionnaire->addMapping(QuestionMapping::forQuestion(
            'map-1',
            'married',
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
        $questionnaire = Questionnaire::create('q-1', '1040-NR', FormType::Form1040Nr);
        $questionnaire->addStep('step-1', 'Personal');
        $questionnaire->addQuestion('step-1', 'question-1', 'married', 'Married?', QuestionType::YesNo);
        $questionnaire->addMapping(QuestionMapping::forQuestion(
            'map-1',
            'married',
            new PdfCoordinates(1, 20.0, 40.0, 10),
        ));

        $this->expectException(InvalidQuestionnaire::class);
        $questionnaire->addMapping(QuestionMapping::forQuestion(
            'map-2',
            'married',
            new PdfCoordinates(1, 10.0, 10.0),
        ));
    }

    public function testVisibilityRuleCanDependOnAnotherQuestion(): void
    {
        $questionnaire = Questionnaire::create('q-1', '1040-NR', FormType::Form1040Nr);
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

    public function testRenameKeepsTheFormType(): void
    {
        $questionnaire = Questionnaire::create('q-1', '1040-NR', FormType::Form1040Nr);
        $questionnaire->rename('1040-NR Demo');

        self::assertSame('1040-NR Demo', $questionnaire->name());
        self::assertSame(FormType::Form1040Nr, $questionnaire->formType());
    }

    public function testOnlyImplementedFormTypesAreExposedToAdmins(): void
    {
        self::assertTrue(FormType::Form1040Nr->isImplemented());
        self::assertFalse(FormType::FormW8Ben->isImplemented());
    }

    public function testFormTypeCanBeChangedBeforeLocking(): void
    {
        $questionnaire = Questionnaire::create('q-1', 'W-8BEN draft', FormType::Form1040Nr);
        $questionnaire->changeFormType(FormType::FormW8Ben, false);

        self::assertSame(FormType::FormW8Ben, $questionnaire->formType());
        self::assertSame('W-8BEN draft', $questionnaire->name());
    }

    public function testFormTypeCannotChangeWhenTheQuestionnaireHasSubmissions(): void
    {
        $questionnaire = Questionnaire::create('q-1', '1040-NR', FormType::Form1040Nr);

        $this->expectException(InvalidQuestionnaire::class);
        $questionnaire->changeFormType(FormType::FormW8Ben, true);
    }

    public function testTheSameFormTypeIsAllowedWhenTheQuestionnaireHasSubmissions(): void
    {
        $questionnaire = Questionnaire::create('q-1', '1040-NR', FormType::Form1040Nr);
        $questionnaire->changeFormType(FormType::Form1040Nr, true);

        self::assertSame(FormType::Form1040Nr, $questionnaire->formType());
    }

    public function testAStepCanBeRenamedAndRemoved(): void
    {
        $questionnaire = $this->questionnaireWithPersonalAndIncomeSteps();
        $questionnaire->renameStep('personal', 'About you');
        $questionnaire->removeStep('income');

        self::assertSame('About you', $questionnaire->findStep('personal')?->title());
        self::assertNull($questionnaire->findStep('income'));
        self::assertSame(1, $questionnaire->steps()[0]->position());
    }

    public function testAStepCanBeRemovedWhenVisibilityOnlyLivesOnThatStep(): void
    {
        $questionnaire = Questionnaire::create('q-1', '1040-NR', FormType::Form1040Nr);
        $questionnaire->addStep('personal', 'Personal');
        $questionnaire->addQuestion('personal', 'q-married', 'married', 'Married?', QuestionType::YesNo);
        $questionnaire->addQuestion(
            'personal',
            'q-spouse',
            'spouse_name',
            'Spouse name',
            QuestionType::ShortText,
            visibility: new VisibilityRule([
                new VisibilityCondition('married', VisibilityOperator::Equals, 'yes'),
            ]),
        );

        $questionnaire->removeStep('personal');

        self::assertSame([], $questionnaire->steps());
    }

    public function testAQuestionCannotBeRemovedWhileVisibilityOrMappingsReferenceIt(): void
    {
        $questionnaire = Questionnaire::create('q-1', '1040-NR', FormType::Form1040Nr);
        $questionnaire->addStep('step-1', 'Personal');
        $questionnaire->addQuestion('step-1', 'q-married', 'married', 'Married?', QuestionType::YesNo);
        $questionnaire->addQuestion(
            'step-1',
            'q-spouse',
            'spouse_name',
            'Spouse name',
            QuestionType::ShortText,
            visibility: new VisibilityRule([
                new VisibilityCondition('married', VisibilityOperator::Equals, 'yes'),
            ]),
        );

        try {
            $questionnaire->removeQuestion('q-married');
            self::fail('Expected a referenced question to stay.');
        } catch (InvalidQuestionnaire $exception) {
            self::assertSame(
                'Question "married" cannot be removed while other questions or PDF mappings still reference it.',
                $exception->getMessage(),
            );
        }

        $questionnaire->removeQuestion('q-spouse');
        $questionnaire->addMapping(QuestionMapping::forQuestion(
            'map-1',
            'married',
            new PdfCoordinates(1, 20.0, 40.0, 10),
        ));

        $this->expectException(InvalidQuestionnaire::class);
        $questionnaire->removeQuestion('q-married');
    }

    public function testVisibilityCannotDependOnAnUnknownQuestionKey(): void
    {
        $questionnaire = Questionnaire::create('q-1', '1040-NR', FormType::Form1040Nr);
        $questionnaire->addStep('step-1', 'Personal');

        $this->expectException(InvalidQuestionnaire::class);
        $this->expectExceptionMessage('Visibility cannot depend on unknown question key "missing".');
        $questionnaire->addQuestion(
            'step-1',
            'q-spouse',
            'spouse_name',
            'Spouse name',
            QuestionType::ShortText,
            visibility: new VisibilityRule([
                new VisibilityCondition('missing', VisibilityOperator::Equals, 'yes'),
            ]),
        );
    }

    private function questionnaireWithPersonalAndIncomeSteps(): Questionnaire
    {
        $questionnaire = Questionnaire::create('q-1', '1040-NR', FormType::Form1040Nr, 'Demo');
        $questionnaire->addStep('personal', 'Personal');
        $questionnaire->addStep('income', 'Income');
        $questionnaire->addQuestion('personal', 'q-married', 'married', 'Married?', QuestionType::YesNo);
        $questionnaire->addQuestion('income', 'q-wages', 'income_wages', 'Wages', QuestionType::Number);

        return $questionnaire;
    }
}
