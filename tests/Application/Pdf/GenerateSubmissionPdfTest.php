<?php

declare(strict_types=1);

namespace App\Tests\Application\Pdf;

use App\Application\Calculation\CalculateSubmission;
use App\Application\Pdf\GenerateSubmissionPdf;
use App\Domain\Calculation\Contract\CalculatorInterface;
use App\Domain\Calculation\DTO\CalculationInput;
use App\Domain\Calculation\DTO\CalculationResult;
use App\Domain\Pdf\Contract\PdfGeneratorInterface;
use App\Domain\Pdf\Exception\PdfGenerationFailed;
use App\Domain\Questionnaire\Entity\QuestionMapping;
use App\Domain\Questionnaire\Entity\Questionnaire;
use App\Domain\Questionnaire\QuestionVisibilityEvaluator;
use App\Domain\Questionnaire\ValueObject\FormType;
use App\Domain\Questionnaire\ValueObject\PdfCoordinates;
use App\Domain\Questionnaire\ValueObject\QuestionType;
use App\Domain\Questionnaire\ValueObject\VisibilityCondition;
use App\Domain\Questionnaire\ValueObject\VisibilityOperator;
use App\Domain\Questionnaire\ValueObject\VisibilityRule;
use App\Domain\Submission\Entity\QuestionnaireSubmission;
use App\Domain\Submission\Exception\InvalidSubmission;
use App\Domain\Submission\ValueObject\AnswerValue;
use App\Domain\Submission\ValueObject\SubmissionStatus;
use App\Domain\User\Entity\User;
use App\Domain\User\ValueObject\Email;
use App\Infrastructure\Calculation\Form1040NrCalculator;
use App\Infrastructure\Filesystem\LocalFileStorage;
use App\Tests\Support\FailingPdfGenerator;
use App\Tests\Support\InMemorySubmissionRepository;
use App\Tests\Support\RecordingPdfGenerator;
use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\TestCase;

final class GenerateSubmissionPdfTest extends TestCase
{
    private InMemorySubmissionRepository $submissions;

    public function testItOverlaysVisibleAnswersAndComputedFieldsUsingMappingCoordinates(): void
    {
        $submission = $this->finalized($this->submission());
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
        $submission = $this->finalized($this->submission());
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
        $questionnaire = Questionnaire::create('q-1', '1040-NR', FormType::Form1040Nr);
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
        $this->finalized($submission);

        $calculator = new class implements CalculatorInterface {
            public bool $called = false;

            public function supports(FormType $formType): bool
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
        $repository = InMemorySubmissionRepository::with($submission);
        $useCase = new GenerateSubmissionPdf(
            $repository,
            new CalculateSubmission($repository, [$calculator]),
            $recorder,
            new QuestionVisibilityEvaluator(),
            new LocalFileStorage(),
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
        $submission = $this->finalized($this->submission());
        $useCase = $this->useCase(
            $submission,
            new RecordingPdfGenerator(),
            sys_get_temp_dir().DIRECTORY_SEPARATOR.'sprintax-pdf-missing-'.uniqid('', true),
            sys_get_temp_dir().DIRECTORY_SEPARATOR.'sprintax-pdf-out-'.uniqid('', true),
        );

        try {
            $useCase->execute('sub-1');
            self::fail('Expected PDF generation to fail when the template is missing.');
        } catch (PdfGenerationFailed) {
            self::assertSame(SubmissionStatus::Finalized, $submission->status());
            self::assertNull($submission->pdfPath());
            self::assertSame(0, $this->submissions->saveCount);
        }
    }

    public function testItMarksPdfReadyAndPersistsTheOutputPath(): void
    {
        $submission = $this->finalized($this->submission());
        $outputDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sprintax-pdf-out-'.uniqid('', true);
        $useCase = $this->useCase(
            $submission,
            new RecordingPdfGenerator(),
            $this->templatesDirectoryWith('1040-nr.pdf'),
            $outputDirectory,
        );

        $outputPath = $useCase->execute('sub-1');

        self::assertSame(SubmissionStatus::PdfReady, $submission->status());
        self::assertSame('sub-1.pdf', $submission->pdfPath());
        self::assertSame($outputDirectory.DIRECTORY_SEPARATOR.'sub-1.pdf', $outputPath);
        self::assertFileExists($outputPath);
        self::assertSame(1, $this->submissions->saveCount);
    }

    public function testItDoesNotRegenerateWhenThePdfIsAlreadyReady(): void
    {
        $submission = $this->finalized($this->submission());
        $recorder = new RecordingPdfGenerator();
        $useCase = $this->useCase(
            $submission,
            $recorder,
            $this->templatesDirectoryWith('1040-nr.pdf'),
            sys_get_temp_dir().DIRECTORY_SEPARATOR.'sprintax-pdf-out-'.uniqid('', true),
        );

        $firstPath = $useCase->execute('sub-1');
        $secondPath = $useCase->execute('sub-1');

        self::assertSame(1, $recorder->calls);
        self::assertSame($firstPath, $secondPath);
        self::assertSame('sub-1.pdf', $submission->pdfPath());
        self::assertSame(SubmissionStatus::PdfReady, $submission->status());
        self::assertSame(1, $this->submissions->saveCount);
    }

    public function testItRegeneratesWhenTheStoredPdfFileIsMissing(): void
    {
        $submission = $this->finalized($this->submission());
        $recorder = new RecordingPdfGenerator();
        $useCase = $this->useCase(
            $submission,
            $recorder,
            $this->templatesDirectoryWith('1040-nr.pdf'),
            sys_get_temp_dir().DIRECTORY_SEPARATOR.'sprintax-pdf-out-'.uniqid('', true),
        );

        $path = $useCase->execute('sub-1');
        unlink($path);

        $regenerated = $useCase->execute('sub-1');

        self::assertSame(2, $recorder->calls);
        self::assertSame($path, $regenerated);
        self::assertSame(SubmissionStatus::PdfReady, $submission->status());
        self::assertSame('sub-1.pdf', $submission->pdfPath());
        self::assertFileExists($regenerated);
        self::assertSame(2, $this->submissions->saveCount);
    }

    public function testItResolvesTheTemplateFromFormTypeAfterARename(): void
    {
        $submission = $this->finalized($this->submission());
        $submission->questionnaire()->rename('1040-NR Demo');
        $recorder = new RecordingPdfGenerator();
        $templates = $this->templatesDirectoryWith('1040-nr.pdf');
        $useCase = $this->useCase(
            $submission,
            $recorder,
            $templates,
            sys_get_temp_dir().DIRECTORY_SEPARATOR.'sprintax-pdf-out-'.uniqid('', true),
        );

        $useCase->execute('sub-1');

        self::assertNotNull($recorder->last);
        self::assertSame($templates.DIRECTORY_SEPARATOR.'1040-nr.pdf', $recorder->last->sourcePdfPath);
        self::assertSame('1040-NR Demo', $submission->questionnaire()->name());
        self::assertSame(FormType::Form1040Nr, $submission->questionnaire()->formType());
    }

    public function testItDoesNotMarkPdfReadyWhenGenerationFails(): void
    {
        $submission = $this->finalized($this->submission());
        $useCase = $this->useCase(
            $submission,
            new FailingPdfGenerator(),
            $this->templatesDirectoryWith('1040-nr.pdf'),
            sys_get_temp_dir().DIRECTORY_SEPARATOR.'sprintax-pdf-out-'.uniqid('', true),
        );

        try {
            $useCase->execute('sub-1');
            self::fail('Expected PDF generation to fail.');
        } catch (PdfGenerationFailed) {
            self::assertSame(SubmissionStatus::Finalized, $submission->status());
            self::assertNull($submission->pdfPath());
            self::assertSame(0, $this->submissions->saveCount);
        }
    }

    public function testItRejectsInProgressSubmissions(): void
    {
        $useCase = $this->useCase(
            $this->submission(),
            new RecordingPdfGenerator(),
            $this->templatesDirectoryWith('1040-nr.pdf'),
            sys_get_temp_dir().DIRECTORY_SEPARATOR.'sprintax-pdf-out-'.uniqid('', true),
        );

        $this->expectException(InvalidSubmission::class);
        $useCase->execute('sub-1');
    }

    private function useCase(
        QuestionnaireSubmission $submission,
        PdfGeneratorInterface $generator,
        string $templatesDirectory,
        string $outputDirectory,
    ): GenerateSubmissionPdf {
        $this->submissions = InMemorySubmissionRepository::with($submission);

        return new GenerateSubmissionPdf(
            $this->submissions,
            new CalculateSubmission($this->submissions, [new Form1040NrCalculator()]),
            $generator,
            new QuestionVisibilityEvaluator(),
            new LocalFileStorage(),
            $templatesDirectory,
            $outputDirectory,
        );
    }

    private function templatesDirectoryWith(string $fileName): string
    {
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sprintax-pdf-tpl-'.uniqid('', true);
        mkdir($directory, 0775, true);
        file_put_contents($directory.DIRECTORY_SEPARATOR.$fileName, '%PDF-1.4 placeholder');

        return $directory;
    }

    private function finalized(QuestionnaireSubmission $submission): QuestionnaireSubmission
    {
        $submission->finalize(new DateTimeImmutable('2026-01-01T11:00:00+00:00'));

        return $submission;
    }

    private function submission(): QuestionnaireSubmission
    {
        $questionnaire = Questionnaire::create('q-1', '1040-NR', FormType::Form1040Nr);
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
