<?php

declare(strict_types=1);

namespace App\Tests\Application\Questionnaire;

use App\Application\Questionnaire\AddQuestion\AddQuestionnaireQuestion;
use App\Domain\Questionnaire\Entity\Questionnaire;
use App\Domain\Questionnaire\ValueObject\FormType;
use App\Domain\Questionnaire\ValueObject\QuestionType;
use App\Domain\Questionnaire\ValueObject\VisibilityOperator;
use App\Tests\Support\InMemoryQuestionnaireRepository;
use PHPUnit\Framework\TestCase;

final class AddQuestionnaireQuestionTest extends TestCase
{
    public function testItMapsFormPrimitivesOntoValidationAndVisibility(): void
    {
        $questionnaire = Questionnaire::create('q-1', '1040-NR', FormType::Form1040Nr);
        $questionnaire->addStep('step-1', 'Personal');
        $questionnaire->addQuestion('step-1', 'q-married', 'married', 'Married?', QuestionType::YesNo);
        $useCase = new AddQuestionnaireQuestion(InMemoryQuestionnaireRepository::with($questionnaire));

        $question = $useCase->execute(
            'q-1',
            'step-1',
            'spouse_name',
            'Spouse name',
            QuestionType::ShortText,
            'Shown when married',
            true,
            2,
            40,
            '^[A-Za-z ]+$',
            'married',
            VisibilityOperator::Equals,
            'yes',
        );

        self::assertTrue($question->validation()->required);
        self::assertSame(2, $question->validation()->min);
        self::assertSame(40, $question->validation()->max);
        self::assertSame('^[A-Za-z ]+$', $question->validation()->regex);
        self::assertFalse($question->visibility()->isAlwaysVisible());
        self::assertSame('married', $question->visibility()->conditions[0]->questionKey);
        self::assertSame(VisibilityOperator::Equals, $question->visibility()->conditions[0]->operator);
        self::assertSame('yes', $question->visibility()->conditions[0]->expectedValue);
    }

    public function testItTreatsBlankRegexAndVisibilityKeyAsUnset(): void
    {
        $questionnaire = Questionnaire::create('q-1', '1040-NR', FormType::Form1040Nr);
        $questionnaire->addStep('step-1', 'Personal');
        $useCase = new AddQuestionnaireQuestion(InMemoryQuestionnaireRepository::with($questionnaire));

        $question = $useCase->execute(
            'q-1',
            'step-1',
            'first_name',
            'First name',
            QuestionType::ShortText,
            null,
            false,
            '1',
            '10',
            '   ',
            '  ',
            VisibilityOperator::NotEquals,
            'ignored',
        );

        self::assertFalse($question->validation()->required);
        self::assertNull($question->validation()->min);
        self::assertNull($question->validation()->max);
        self::assertNull($question->validation()->regex);
        self::assertTrue($question->visibility()->isAlwaysVisible());
    }

    public function testItTreatsAMissingOperatorAsAlwaysVisible(): void
    {
        $questionnaire = Questionnaire::create('q-1', '1040-NR', FormType::Form1040Nr);
        $questionnaire->addStep('step-1', 'Personal');
        $useCase = new AddQuestionnaireQuestion(InMemoryQuestionnaireRepository::with($questionnaire));

        $question = $useCase->execute(
            'q-1',
            'step-1',
            'spouse_name',
            'Spouse name',
            QuestionType::ShortText,
            null,
            false,
            null,
            null,
            null,
            'married',
            null,
            'yes',
        );

        self::assertTrue($question->visibility()->isAlwaysVisible());
    }
}
