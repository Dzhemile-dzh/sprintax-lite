<?php

declare(strict_types=1);

namespace App\Tests\Application\Pdf;

use App\Application\Calculation\CalculateSubmission;
use App\Application\Pdf\GenerateSubmissionPdf;
use App\Domain\Calculation\Contract\CalculatorInterface;
use App\Domain\Calculation\DTO\CalculationInput;
use App\Domain\Calculation\DTO\CalculationResult;
use App\Domain\Pdf\Contract\PdfGeneratorInterface;
use App\Domain\Pdf\DTO\PdfGenerationRequest;
use App\Domain\Pdf\Exception\PdfGenerationFailed;
use App\Domain\Questionnaire\Entity\QuestionMapping;
use App\Domain\Questionnaire\Entity\Questionnaire;
use App\Domain\Questionnaire\QuestionVisibilityEvaluator;
use App\Domain\Questionnaire\ValueObject\PdfCoordinates;
use App\Domain\Questionnaire\ValueObject\QuestionType;
use App\Domain\Questionnaire\ValueObject\VisibilityCondition;
use App\Domain\Questionnaire\ValueObject\VisibilityOperator;
use App\Domain\Questionnaire\ValueObject\VisibilityRule;
use App\Domain\Submission\Entity\QuestionnaireSubmission;
use App\Domain\Submission\Exception\SubmissionNotFound;
use App\Domain\Submission\Repository\SubmissionRepositoryInterface;
use App\Domain\Submission\ValueObject\AnswerValue;
use App\Domain\User\Entity\User;
use App\Domain\User\ValueObject\Email;
use App\Infrastructure\Calculation\Form1040NrCalculator;
use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\TestCase;

final class GenerateSubmissionPdfTest extends TestCase
{
    public function testItOverlaysVisibleAnswersAndComputedFieldsUsingMappingCoordinates(): void
    {
        $submission = $this->submission();
        $recorder = new RecordingPdfGenerator();
        $templates = $this->templatesDirectoryWith('1040-nr.pdf');
        $outputDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sprintax-pdf-out-'.uniqid('', true);

        $useCase = $this->useCase($submission, $recorder, $templates, $outputDirectory);
        $outputPath = $useCase->execute('sub-1');

        self::assertSame($outputDirectory.DIRECTORY_SEPARATOR.'sub-1.pdf', $outputPath);
        self::assertNotNull($recorder->last);
        self::assertSame($templates.DIRECTORY_SEPARATOR.'1040-nr.pdf', $recorder->last->sourcePdfPath);
        self::assertSame($outputPath, $recorder->last->outputPath);
        self::assertCount(3, $recorder->last->fields);

        $firstName = $recorder->last->fields[0];
        self::assertSame('Ada', $firstName['value']);
        self::assertSame(1, $firstName['placement']->page);
        self::assertSame(20.5, $firstName['placement']->xMm);
        self::assertSame(40.25, $firstName['placement']->yMm);
        self::assertSame(11, $firstName['placement']->fontSize);

        $incomeTypes = $recorder->last->fields[1];
        self::assertSame('wages, treaty', $incomeTypes['value']);
        self::assertSame(1, $incomeTypes['placement']->page);
        self::assertSame(15.0, $incomeTypes['placement']->xMm);
        self::assertSame(60.0, $incomeTypes['placement']->yMm);
        self::assertNull($incomeTypes['placement']->fontSize);

        $taxOwed = $recorder->last->fields[2];
        self::assertSame('5000.00', $taxOwed['value']);
        self::assertSame(2, $taxOwed['placement']->page);
        self::assertSame(100.0, $taxOwed['placement']->xMm);
        self::assertSame(180.5, $taxOwed['placement']->yMm);
        self::assertSame(9, $taxOwed['placement']->fontSize);
    }

    public function testItOmitsHiddenAndBlankAnswers(): void
    {
        $submission = $this->submission();
        $recorder = new RecordingPdfGenerator();
        $useCase = $this->useCase(
            $submission,
            $recorder,
            $this->templatesDirectoryWith('1040-nr.pdf'),
            sys_get_temp_dir().DIRECTORY_SEPARATOR.'sprintax-pdf-out-'.uniqid('', true),
        );

        $useCase->execute('sub-1');

        self::assertNotNull($recorder->last);
        $values = array_map(
            static fn (array $field): string => $field['value'],
            $recorder->last->fields,
        );

        self::assertNotContains('hidden-spouse', $values);
        self::assertNotContains('', $values);
        self::assertNotContains('   ', $values);
    }

    public function testItDoesNotCalculateWhenThereAreNoComputedMappings(): void
    {
        $questionnaire = Questionnaire::create('q-1', '1040-NR');
        $questionnaire->addStep('step-1', 'Personal');
        $firstName = $questionnaire->addQuestion(
            'step-1',
            'q-name',
            'first_name',
            'First name',
            QuestionType::ShortText,
        );
        $questionnaire->addMapping(QuestionMapping::forQuestion(
            'map-name',
            $firstName->id(),
            new PdfCoordinates(1, 10.0, 10.0),
        ));

        $submission = QuestionnaireSubmission::start(
            'sub-1',
            $questionnaire,
            User::registerClient('user-1', new Email('client@example.test'), 'hashed-password'),
            new DateTimeImmutable('2026-01-01T10:00:00+00:00'),
        );
        $submission->recordAnswer(
            $firstName,
            AnswerValue::text('Ada'),
            new DateTimeImmutable('2026-01-01T10:05:00+00:00'),
        );

        $calculator = new class implements CalculatorInterface {
            public bool $called = false;

            public function supports(string $formType): bool
            {
                $this->called = true;

                return true;
            }

            public function calculate(CalculationInput $input): CalculationResult
            {
                throw new LogicException('Calculation should not run without computed mappings.');
            }
        };

        $recorder = new RecordingPdfGenerator();
        $useCase = new GenerateSubmissionPdf(
            $this->submissions($submission),
            new CalculateSubmission($this->submissions($submission), [$calculator]),
            $recorder,
            new QuestionVisibilityEvaluator(),
            $this->templatesDirectoryWith('1040-nr.pdf'),
            sys_get_temp_dir().DIRECTORY_SEPARATOR.'sprintax-pdf-out-'.uniqid('', true),
        );

        $useCase->execute('sub-1');

        self::assertFalse($calculator->called);
        self::assertNotNull($recorder->last);
        self::assertCount(1, $recorder->last->fields);
        self::assertSame('Ada', $recorder->last->fields[0]['value']);
    }

    public function testItFailsWhenTheTemplateFileIsMissing(): void
    {
        $submission = $this->submission();
        $useCase = $this->useCase(
            $submission,
            new RecordingPdfGenerator(),
            sys_get_temp_dir().DIRECTORY_SEPARATOR.'sprintax-pdf-missing-'.uniqid('', true),
            sys_get_temp_dir().DIRECTORY_SEPARATOR.'sprintax-pdf-out-'.uniqid('', true),
        );

        $this->expectException(PdfGenerationFailed::class);
        $useCase->execute('sub-1');
    }

    private function useCase(
        QuestionnaireSubmission $submission,
        PdfGeneratorInterface $generator,
        string $templatesDirectory,
        string $outputDirectory,
    ): GenerateSubmissionPdf {
        $repository = $this->submissions($submission);

        return new GenerateSubmissionPdf(
            $repository,
            new CalculateSubmission($repository, [new Form1040NrCalculator()]),
            $generator,
            new QuestionVisibilityEvaluator(),
            $templatesDirectory,
            $outputDirectory,
        );
    }

    private function submissions(QuestionnaireSubmission $submission): SubmissionRepositoryInterface
    {
        return new class([$submission->id() => $submission]) implements SubmissionRepositoryInterface {
            /**
             * @param array<string, QuestionnaireSubmission> $items
             */
            public function __construct(
                private array $items,
            ) {
            }

            public function get(string $id): QuestionnaireSubmission
            {
                $submission = $this->items[$id] ?? null;

                if (!$submission instanceof QuestionnaireSubmission) {
                    throw SubmissionNotFound::withId($id);
                }

                return $submission;
            }

            public function save(QuestionnaireSubmission $submission): void
            {
                $this->items[$submission->id()] = $submission;
            }
        };
    }

    private function templatesDirectoryWith(string $fileName): string
    {
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sprintax-pdf-tpl-'.uniqid('', true);
        mkdir($directory, 0775, true);
        file_put_contents($directory.DIRECTORY_SEPARATOR.$fileName, '%PDF-1.4 placeholder');

        return $directory;
    }

    private function submission(): QuestionnaireSubmission
    {
        $questionnaire = Questionnaire::create('q-1', '1040-NR');
        $questionnaire->addStep('step-1', 'Personal');
        $firstName = $questionnaire->addQuestion(
            'step-1',
            'q-name',
            'first_name',
            'First name',
            QuestionType::ShortText,
        );
        $married = $questionnaire->addQuestion(
            'step-1',
            'q-married',
            'married',
            'Married?',
            QuestionType::YesNo,
        );
        $spouseName = $questionnaire->addQuestion(
            'step-1',
            'q-spouse',
            'spouse_name',
            'Spouse name',
            QuestionType::ShortText,
            visibility: new VisibilityRule([
                new VisibilityCondition('married', VisibilityOperator::Equals, 'yes'),
            ]),
        );
        $incomeTypes = $questionnaire->addQuestion(
            'step-1',
            'q-types',
            'income_types',
            'Income types',
            QuestionType::MultiChoice,
        );
        $notes = $questionnaire->addQuestion(
            'step-1',
            'q-notes',
            'notes',
            'Notes',
            QuestionType::ShortText,
        );
        $wages = $questionnaire->addQuestion(
            'step-1',
            'q-wages',
            'income_wages',
            'Wages',
            QuestionType::Number,
        );

        $questionnaire->addMapping(QuestionMapping::forQuestion(
            'map-name',
            $firstName->id(),
            new PdfCoordinates(1, 20.5, 40.25, 11),
        ));
        $questionnaire->addMapping(QuestionMapping::forQuestion(
            'map-spouse',
            $spouseName->id(),
            new PdfCoordinates(1, 20.5, 50.0, 11),
        ));
        $questionnaire->addMapping(QuestionMapping::forQuestion(
            'map-types',
            $incomeTypes->id(),
            new PdfCoordinates(1, 15.0, 60.0),
        ));
        $questionnaire->addMapping(QuestionMapping::forQuestion(
            'map-notes',
            $notes->id(),
            new PdfCoordinates(1, 15.0, 70.0),
        ));
        $questionnaire->addMapping(QuestionMapping::forComputedField(
            'map-tax',
            Form1040NrCalculator::FIELD_TAX_OWED,
            new PdfCoordinates(2, 100.0, 180.5, 9),
        ));

        $now = new DateTimeImmutable('2026-01-01T10:00:00+00:00');
        $submission = QuestionnaireSubmission::start(
            'sub-1',
            $questionnaire,
            User::registerClient('user-1', new Email('client@example.test'), 'hashed-password'),
            $now,
        );
        $submission->recordAnswer($firstName, AnswerValue::text('Ada'), $now);
        $submission->recordAnswer($married, AnswerValue::text('no'), $now);
        $submission->recordAnswer($spouseName, AnswerValue::text('hidden-spouse'), $now);
        $submission->recordAnswer($incomeTypes, AnswerValue::choices(['wages', 'treaty']), $now);
        $submission->recordAnswer($notes, AnswerValue::text('   '), $now);
        $submission->recordAnswer($wages, AnswerValue::text('50000'), $now);

        return $submission;
    }
}

final class RecordingPdfGenerator implements PdfGeneratorInterface
{
    public ?PdfGenerationRequest $last = null;

    public function generate(PdfGenerationRequest $request): void
    {
        $this->last = $request;
    }
}
