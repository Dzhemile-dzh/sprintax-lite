<?php

declare(strict_types=1);

namespace App\Tests\Application\Questionnaire;

use App\Application\Calculation\ListCalculatorFields;
use App\Application\Questionnaire\AddMapping\AddQuestionMapping;
use App\Domain\Questionnaire\Entity\Questionnaire;
use App\Domain\Questionnaire\Exception\InvalidQuestionnaire;
use App\Domain\Questionnaire\ValueObject\FormType;
use App\Domain\Questionnaire\ValueObject\MappingSourceType;
use App\Domain\Questionnaire\ValueObject\QuestionType;
use App\Infrastructure\Calculation\Form1040NrCalculator;
use App\Tests\Support\InMemoryQuestionnaireRepository;
use App\Tests\Support\TestRevisionRecorder;
use PHPUnit\Framework\TestCase;

final class AddQuestionMappingTest extends TestCase
{
    public function testItStoresAQuestionMappingByKey(): void
    {
        $questionnaire = $this->questionnaire();
        $this->useCase($questionnaire)->execute(
            'q-1',
            MappingSourceType::Question,
            'first_name',
            1,
            20.5,
            40.25,
            11,
        );

        $mapping = $questionnaire->mappings()[0];
        self::assertTrue($mapping->source()->isQuestion());
        self::assertSame('first_name', $mapping->source()->reference);
    }

    public function testItRejectsAnUnknownComputedField(): void
    {
        $questionnaire = $this->questionnaire();

        $this->expectException(InvalidQuestionnaire::class);
        $this->expectExceptionMessage('"not_a_field" is not a computed field for this form type.');
        $this->useCase($questionnaire)->execute(
            'q-1',
            MappingSourceType::ComputedField,
            'not_a_field',
            1,
            10.0,
            10.0,
            null,
        );
    }

    public function testItAcceptsACalculatorOutputField(): void
    {
        $questionnaire = $this->questionnaire();
        $this->useCase($questionnaire)->execute(
            'q-1',
            MappingSourceType::ComputedField,
            Form1040NrCalculator::FIELD_TAX_OWED,
            2,
            100.0,
            180.5,
            9,
        );

        self::assertSame(Form1040NrCalculator::FIELD_TAX_OWED, $questionnaire->mappings()[0]->source()->reference);
    }

    private function useCase(Questionnaire $questionnaire): AddQuestionMapping
    {
        return new AddQuestionMapping(
            InMemoryQuestionnaireRepository::with($questionnaire),
            new ListCalculatorFields([new Form1040NrCalculator()]),
            TestRevisionRecorder::create(),
        );
    }

    private function questionnaire(): Questionnaire
    {
        $questionnaire = Questionnaire::create('q-1', '1040-NR', FormType::Form1040Nr);
        $questionnaire->addStep('step-1', 'Personal');
        $questionnaire->addQuestion('step-1', 'q-name', 'first_name', 'First name', QuestionType::ShortText);

        return $questionnaire;
    }
}
