<?php

declare(strict_types=1);

namespace App\Tests\Domain\Audit;

use App\Domain\Audit\QuestionnaireStructureSnapshot;
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
use PHPUnit\Framework\TestCase;

final class QuestionnaireStructureSnapshotTest extends TestCase
{
    public function testItCapturesStepsQuestionsOptionsAndMappings(): void
    {
        $snapshot = (new QuestionnaireStructureSnapshot())->of($this->questionnaire());

        self::assertSame('1040-NR', $snapshot->name);
        self::assertSame('1040-nr', $snapshot->formType);
        self::assertSame('Demo', $snapshot->description);
        self::assertCount(2, $snapshot->steps);
        self::assertSame(3, $snapshot->questionCount());

        self::assertSame('Personal', $snapshot->steps[0]->title);
        self::assertSame(1, $snapshot->steps[0]->position);
        self::assertSame('married', $snapshot->steps[0]->questions[0]->key);
        self::assertSame('yes_no', $snapshot->steps[0]->questions[0]->type);
        self::assertTrue($snapshot->steps[0]->questions[0]->required);

        $spouse = $snapshot->steps[0]->questions[1];
        self::assertSame('spouse_name', $spouse->key);
        self::assertSame('married equals "yes"', $spouse->conditions[0]->describe());

        $residency = $snapshot->steps[1]->questions[0];
        self::assertSame('residency', $residency->key);
        self::assertSame(['Resident', 'Nonresident'], array_column($residency->options, 'label'));

        self::assertCount(1, $snapshot->mappings);
        self::assertSame('question', $snapshot->mappings[0]->sourceType);
        self::assertSame('married', $snapshot->mappings[0]->sourceReference);
        self::assertSame(2, $snapshot->mappings[0]->page);
        self::assertSame(20.5, $snapshot->mappings[0]->xMm);
        self::assertSame(9, $snapshot->mappings[0]->fontSize);
    }

    public function testItCapturesValidationBoundsAndPattern(): void
    {
        $questionnaire = Questionnaire::create('q-1', '1040-NR', FormType::Form1040Nr);
        $questionnaire->addStep('step-1', 'Personal');
        $questionnaire->addQuestion(
            'step-1',
            'q-name',
            'first_name',
            'First name',
            QuestionType::ShortText,
            'As shown on your passport.',
            new QuestionValidation(true, 2, 40, '^[A-Za-z ]+$'),
        );

        $question = (new QuestionnaireStructureSnapshot())->of($questionnaire)->steps[0]->questions[0];

        self::assertSame('As shown on your passport.', $question->helpText);
        self::assertSame(2, $question->min);
        self::assertSame(40, $question->max);
        self::assertSame('^[A-Za-z ]+$', $question->regex);
        self::assertSame([], $question->conditions);
    }

    private function questionnaire(): Questionnaire
    {
        $questionnaire = Questionnaire::create('q-1', '1040-NR', FormType::Form1040Nr, 'Demo');
        $questionnaire->addStep('step-1', 'Personal');
        $questionnaire->addStep('step-2', 'Income');
        $questionnaire->addQuestion(
            'step-1',
            'q-married',
            'married',
            'Married?',
            QuestionType::YesNo,
            null,
            QuestionValidation::required(),
        );
        $questionnaire->addQuestion(
            'step-1',
            'q-spouse',
            'spouse_name',
            'Spouse name',
            QuestionType::ShortText,
            null,
            null,
            new VisibilityRule([
                new VisibilityCondition('married', VisibilityOperator::Equals, 'yes'),
            ]),
        );
        $residency = $questionnaire->addQuestion(
            'step-2',
            'q-residency',
            'residency',
            'Residency status',
            QuestionType::SingleChoice,
        );
        $residency->addOption(QuestionOption::create('opt-1', 'Resident', 'resident', 1));
        $residency->addOption(QuestionOption::create('opt-2', 'Nonresident', 'nonresident', 2));
        $questionnaire->addMapping(QuestionMapping::forQuestion(
            'map-1',
            'married',
            new PdfCoordinates(2, 20.5, 40.25, 9),
        ));

        return $questionnaire;
    }
}
