<?php

declare(strict_types=1);

namespace App\Tests\Application\Pdf;

use App\Application\Calculation\CalculateSubmission;
use App\Application\Pdf\EmailSubmissionPdf;
use App\Application\Pdf\GenerateSubmissionPdf;
use App\Application\Pdf\SubmissionPdfMailer;
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
use App\Tests\Support\FailingSubmissionPdfMailer;
use App\Tests\Support\InMemoryQuestionnaireRepository;
use App\Tests\Support\InMemorySubmissionRepository;
use App\Tests\Support\RecordingPdfGenerator;
use App\Tests\Support\RecordingSubmissionPdfMailer;
use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\TestCase;

final class GenerateSubmissionPdfTest extends TestCase
{
    private InMemorySubmissionRepository $submissions;

    private RecordingSubmissionPdfMailer $mailer;

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
        self::assertCount(4, $recorder->last->fields);

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

        $wages = $recorder->last->fields[2];
        self::assertSame('50000.00', $wages['value']);

        $taxOwed = $recorder->last->fields[3];
        self::assertSame('5000.00', $taxOwed['value']);
        self::assertSame(2, $taxOwed['placement']->page);
        self::assertSame(100.0, $taxOwed['placement']->xMm);
        self::assertSame(180.5, $taxOwed['placement']->yMm);
        self::assertSame(9, $taxOwed['placement']->fontSize);
    }

    public function testItOverlaysUsingMappingsFromTheQuestionnaireBuilder(): void
    {
        $onSubmission = Questionnaire::create('q-1', '1040-NR', FormType::Form1040Nr);
        $onSubmission->addStep('step-1', 'Personal');
        $firstName = $onSubmission->addQuestion(
            'step-1',
            'q-name',
            'first_name',
            'First name',
            QuestionType::ShortText,
        );
        $onSubmission->addMapping(QuestionMapping::forQuestion(
            'map-stale',
            $firstName->key(),
            new PdfCoordinates(1, 1.0, 1.0, 8),
        ));

        $fromAdmin = Questionnaire::create('q-1', '1040-NR', FormType::Form1040Nr);
        $fromAdmin->addStep('step-1', 'Personal');
        $fromAdmin->addQuestion(
            'step-1',
            'q-name',
            'first_name',
            'First name',
            QuestionType::ShortText,
        );
        $fromAdmin->addMapping(QuestionMapping::forQuestion(
            'map-admin',
            'first_name',
            new PdfCoordinates(1, 17.0, 40.3, 9),
        ));

        $submission = QuestionnaireSubmission::start(
            'sub-1',
            $onSubmission,
            User::registerClient('user-1', new Email('client@example.test'), 'hashed-password'),
            new DateTimeImmutable('2026-01-01T10:00:00+00:00'),
        );
        $submission->recordAnswer(
            $firstName,
            AnswerValue::text('Ada'),
            new DateTimeImmutable('2026-01-01T10:05:00+00:00'),
        );
        $this->finalized($submission);

        $recorder = new RecordingPdfGenerator();
        $repository = InMemorySubmissionRepository::with($submission);
        $outputDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sprintax-pdf-out-'.uniqid('', true);
        $useCase = new GenerateSubmissionPdf(
            $repository,
            InMemoryQuestionnaireRepository::with($fromAdmin),
            new CalculateSubmission($repository, [new Form1040NrCalculator()]),
            $recorder,
            new QuestionVisibilityEvaluator(),
            new LocalFileStorage(),
            $this->emailer($repository, $outputDirectory, new RecordingSubmissionPdfMailer()),
            $this->templatesDirectoryWith('1040-nr.pdf'),
            $outputDirectory,
        );

        $useCase->execute('sub-1');

        self::assertNotNull($recorder->last);
        self::assertCount(1, $recorder->last->fields);
        self::assertSame('Ada', $recorder->last->fields[0]['value']);
        self::assertSame(17.0, $recorder->last->fields[0]['placement']->xMm);
        self::assertSame(40.3, $recorder->last->fields[0]['placement']->yMm);
        self::assertSame(9, $recorder->last->fields[0]['placement']->fontSize);
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

    public function testItFormatsStoredDatesAsMonthDayYear(): void
    {
        $questionnaire = Questionnaire::create('q-1', '1040-NR', FormType::Form1040Nr);
        $questionnaire->addStep('step-1', 'Personal');
        $birthDate = $questionnaire->addQuestion(
            'step-1',
            'q-dob',
            'birth_date',
            'Date of birth',
            QuestionType::Date,
        );
        $questionnaire->addMapping(QuestionMapping::forQuestion(
            'map-dob',
            $birthDate->key(),
            new PdfCoordinates(1, 168.0, 43.0, 8),
        ));

        $submission = QuestionnaireSubmission::start(
            'sub-1',
            $questionnaire,
            User::registerClient('user-1', new Email('client@example.test'), 'hashed-password'),
            new DateTimeImmutable('2026-01-01T10:00:00+00:00'),
        );
        $submission->recordAnswer(
            $birthDate,
            AnswerValue::text('1995-07-12'),
            new DateTimeImmutable('2026-01-01T10:05:00+00:00'),
        );
        $this->finalized($submission);

        $recorder = new RecordingPdfGenerator();
        $useCase = $this->useCase(
            $submission,
            $recorder,
            $this->templatesDirectoryWith('1040-nr.pdf'),
            sys_get_temp_dir().DIRECTORY_SEPARATOR.'sprintax-pdf-out-'.uniqid('', true),
        );

        $useCase->execute('sub-1');

        self::assertNotNull($recorder->last);
        self::assertCount(1, $recorder->last->fields);
        self::assertSame('07/12/1995', $recorder->last->fields[0]['value']);
    }

    public function testItFailsWhenAStoredDateIsNotCanonical(): void
    {
        $questionnaire = Questionnaire::create('q-1', '1040-NR', FormType::Form1040Nr);
        $questionnaire->addStep('step-1', 'Personal');
        $birthDate = $questionnaire->addQuestion(
            'step-1',
            'q-dob',
            'birth_date',
            'Date of birth',
            QuestionType::Date,
        );
        $questionnaire->addMapping(QuestionMapping::forQuestion(
            'map-dob',
            $birthDate->key(),
            new PdfCoordinates(1, 168.0, 43.0, 8),
        ));

        $submission = QuestionnaireSubmission::start(
            'sub-1',
            $questionnaire,
            User::registerClient('user-1', new Email('client@example.test'), 'hashed-password'),
            new DateTimeImmutable('2026-01-01T10:00:00+00:00'),
        );
        $submission->recordAnswer(
            $birthDate,
            AnswerValue::text('12/07/1995'),
            new DateTimeImmutable('2026-01-01T10:05:00+00:00'),
        );
        $this->finalized($submission);

        $useCase = $this->useCase(
            $submission,
            new RecordingPdfGenerator(),
            $this->templatesDirectoryWith('1040-nr.pdf'),
            sys_get_temp_dir().DIRECTORY_SEPARATOR.'sprintax-pdf-out-'.uniqid('', true),
        );

        try {
            $useCase->execute('sub-1');
            self::fail('Expected PDF generation to fail when a stored date is not canonical.');
        } catch (PdfGenerationFailed $exception) {
            self::assertFalse($exception->isRetryable());
            self::assertSame(
                'PDF mapping has an invalid stored date "12/07/1995".',
                $exception->getMessage(),
            );
            self::assertSame(SubmissionStatus::Finalized, $submission->status());
            self::assertNull($submission->pdfPath());
            self::assertSame(0, $this->submissions->saveCount);
        }
    }

    public function testItIncludesHiddenAndBlankMappingPagesInTheRequest(): void
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
        $notes = $questionnaire->addQuestion(
            'step-1',
            'q-notes',
            'notes',
            'Notes',
            QuestionType::ShortText,
        );
        $questionnaire->addMapping(QuestionMapping::forQuestion(
            'map-name',
            $firstName->key(),
            new PdfCoordinates(1, 20.5, 40.25, 11),
        ));
        $questionnaire->addMapping(QuestionMapping::forQuestion(
            'map-spouse',
            $spouseName->key(),
            new PdfCoordinates(3, 20.5, 50.0, 11),
        ));
        $questionnaire->addMapping(QuestionMapping::forQuestion(
            'map-notes',
            $notes->key(),
            new PdfCoordinates(4, 15.0, 70.0),
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
        $submission->recordAnswer($notes, AnswerValue::text('   '), $now);
        $this->finalized($submission);

        $recorder = new RecordingPdfGenerator();
        $useCase = $this->useCase(
            $submission,
            $recorder,
            $this->templatesDirectoryWith('1040-nr.pdf'),
            sys_get_temp_dir().DIRECTORY_SEPARATOR.'sprintax-pdf-out-'.uniqid('', true),
        );

        $useCase->execute('sub-1');

        self::assertNotNull($recorder->last);
        self::assertSame([1, 3, 4], $recorder->last->mappedPages);
        self::assertCount(1, $recorder->last->fields);
        self::assertSame('Ada', $recorder->last->fields[0]['value']);
    }

    public function testItOmitsTheUnusedZeroBalanceLine(): void
    {
        $questionnaire = Questionnaire::create('q-1', '1040-NR', FormType::Form1040Nr);
        $questionnaire->addStep('step-1', 'Income');
        $wages = $questionnaire->addQuestion(
            'step-1',
            'q-wages',
            'income_wages',
            'Wages',
            QuestionType::Number,
        );
        $withheld = $questionnaire->addQuestion(
            'step-1',
            'q-withheld',
            'tax_withheld',
            'Tax withheld',
            QuestionType::Number,
        );
        $questionnaire->addMapping(QuestionMapping::forComputedField(
            'map-tax',
            Form1040NrCalculator::FIELD_TAX_OWED,
            new PdfCoordinates(2, 188.0, 52.1, 9),
        ));
        $questionnaire->addMapping(QuestionMapping::forComputedField(
            'map-overpaid',
            Form1040NrCalculator::FIELD_AMOUNT_OVERPAID,
            new PdfCoordinates(2, 188.0, 177.0, 9),
        ));
        $questionnaire->addMapping(QuestionMapping::forComputedField(
            'map-owed',
            Form1040NrCalculator::FIELD_AMOUNT_OWED,
            new PdfCoordinates(2, 188.0, 208.7, 9),
        ));

        $now = new DateTimeImmutable('2026-01-01T10:00:00+00:00');
        $submission = QuestionnaireSubmission::start(
            'sub-1',
            $questionnaire,
            User::registerClient('user-1', new Email('client@example.test'), 'hashed-password'),
            $now,
        );
        $submission->recordAnswer($wages, AnswerValue::text('20000'), $now);
        $submission->recordAnswer($withheld, AnswerValue::text('2500'), $now);
        $this->finalized($submission);

        $recorder = new RecordingPdfGenerator();
        $useCase = $this->useCase(
            $submission,
            $recorder,
            $this->templatesDirectoryWith('1040-nr.pdf'),
            sys_get_temp_dir().DIRECTORY_SEPARATOR.'sprintax-pdf-out-'.uniqid('', true),
        );

        $useCase->execute('sub-1');

        self::assertNotNull($recorder->last);
        self::assertCount(2, $recorder->last->fields);

        $tax = null;
        $overpaid = null;
        $owed = null;
        foreach ($recorder->last->fields as $field) {
            $yMm = $field['placement']->yMm;

            if ($yMm === 52.1) {
                $tax = $field['value'];
            }

            if ($yMm === 177.0) {
                $overpaid = $field['value'];
            }

            if ($yMm === 208.7) {
                $owed = $field['value'];
            }
        }

        self::assertSame('2000.00', $tax);
        self::assertSame('500.00', $overpaid);
        self::assertNull($owed);
    }

    public function testItStillPrintsZeroOnTaxAndIncomeLines(): void
    {
        $questionnaire = Questionnaire::create('q-1', '1040-NR', FormType::Form1040Nr);
        $questionnaire->addStep('step-1', 'Income');
        $wages = $questionnaire->addQuestion(
            'step-1',
            'q-wages',
            'income_wages',
            'Wages',
            QuestionType::Number,
        );
        $treaty = $questionnaire->addQuestion(
            'step-1',
            'q-treaty',
            'treaty_exempt_amount',
            'Treaty exemption',
            QuestionType::Number,
        );
        $questionnaire->addMapping(QuestionMapping::forComputedField(
            'map-agi',
            Form1040NrCalculator::FIELD_ADJUSTED_GROSS_INCOME,
            new PdfCoordinates(1, 188.0, 257.2, 9),
        ));
        $questionnaire->addMapping(QuestionMapping::forComputedField(
            'map-tax',
            Form1040NrCalculator::FIELD_TAX_OWED,
            new PdfCoordinates(2, 188.0, 52.1, 9),
        ));
        $questionnaire->addMapping(QuestionMapping::forComputedField(
            'map-owed',
            Form1040NrCalculator::FIELD_AMOUNT_OWED,
            new PdfCoordinates(2, 188.0, 208.7, 9),
        ));

        $now = new DateTimeImmutable('2026-01-01T10:00:00+00:00');
        $submission = QuestionnaireSubmission::start(
            'sub-1',
            $questionnaire,
            User::registerClient('user-1', new Email('client@example.test'), 'hashed-password'),
            $now,
        );
        $submission->recordAnswer($wages, AnswerValue::text('1000'), $now);
        $submission->recordAnswer($treaty, AnswerValue::text('5000'), $now);
        $this->finalized($submission);

        $recorder = new RecordingPdfGenerator();
        $useCase = $this->useCase(
            $submission,
            $recorder,
            $this->templatesDirectoryWith('1040-nr.pdf'),
            sys_get_temp_dir().DIRECTORY_SEPARATOR.'sprintax-pdf-out-'.uniqid('', true),
        );

        $useCase->execute('sub-1');

        self::assertNotNull($recorder->last);

        $agi = null;
        $tax = null;
        $owed = null;
        foreach ($recorder->last->fields as $field) {
            $yMm = $field['placement']->yMm;

            if ($yMm === 257.2) {
                $agi = $field['value'];
            }

            if ($yMm === 52.1) {
                $tax = $field['value'];
            }

            if ($yMm === 208.7) {
                $owed = $field['value'];
            }
        }

        self::assertSame('0.00', $agi);
        self::assertSame('0.00', $tax);
        self::assertNull($owed);
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
            $firstName->key(),
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

            public function outputFields(): array
            {
                return [];
            }

            public function calculate(CalculationInput $input): CalculationResult
            {
                throw new LogicException('Calculation should not run without computed mappings.');
            }
        };

        $recorder = new RecordingPdfGenerator();
        $repository = InMemorySubmissionRepository::with($submission);
        $outputDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sprintax-pdf-out-'.uniqid('', true);
        $useCase = new GenerateSubmissionPdf(
            $repository,
            InMemoryQuestionnaireRepository::with($questionnaire),
            new CalculateSubmission($repository, [$calculator]),
            $recorder,
            new QuestionVisibilityEvaluator(),
            new LocalFileStorage(),
            $this->emailer($repository, $outputDirectory, new RecordingSubmissionPdfMailer()),
            $this->templatesDirectoryWith('1040-nr.pdf'),
            $outputDirectory,
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
        self::assertSame(2, $this->submissions->saveCount);
        self::assertTrue($submission->pdfWasEmailed());
        self::assertCount(1, $this->mailer->sent);
        self::assertSame('client@example.test', $this->mailer->sent[0]['recipientEmail']);
        self::assertSame('1040-NR', $this->mailer->sent[0]['questionnaireName']);
        self::assertSame($outputPath, $this->mailer->sent[0]['absolutePdfPath']);
        self::assertSame('sub-1.pdf', $this->mailer->sent[0]['downloadFileName']);
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
        self::assertSame(2, $this->submissions->saveCount);
        self::assertCount(1, $this->mailer->sent);
        self::assertTrue($submission->pdfWasEmailed());
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
        self::assertSame(3, $this->submissions->saveCount);
        self::assertCount(1, $this->mailer->sent);
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

    public function testAMailerFailureLeavesThePdfReadyForDownload(): void
    {
        $submission = $this->finalized($this->submission());
        $outputDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sprintax-pdf-out-'.uniqid('', true);
        $useCase = $this->useCase(
            $submission,
            new RecordingPdfGenerator(),
            $this->templatesDirectoryWith('1040-nr.pdf'),
            $outputDirectory,
            new FailingSubmissionPdfMailer(),
        );

        try {
            $useCase->execute('sub-1');
            self::fail('Expected emailing the PDF to fail.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Mail transport failed.', $exception->getMessage());
            self::assertSame(SubmissionStatus::PdfReady, $submission->status());
            self::assertSame('sub-1.pdf', $submission->pdfPath());
            self::assertFalse($submission->pdfWasEmailed());
            self::assertFileExists($outputDirectory.DIRECTORY_SEPARATOR.'sub-1.pdf');
            self::assertSame(1, $this->submissions->saveCount);
        }
    }

    public function testItEmailsWhenThePdfAlreadyExistsButWasNotSent(): void
    {
        $submission = $this->finalized($this->submission());
        $outputDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sprintax-pdf-out-'.uniqid('', true);
        mkdir($outputDirectory, 0775, true);
        $outputPath = $outputDirectory.DIRECTORY_SEPARATOR.'sub-1.pdf';
        file_put_contents($outputPath, '%PDF-1.4');
        $submission->markPdfReady(new DateTimeImmutable('2026-01-01T11:05:00+00:00'), 'sub-1.pdf');
        $recorder = new RecordingPdfGenerator();
        $useCase = $this->useCase(
            $submission,
            $recorder,
            $this->templatesDirectoryWith('1040-nr.pdf'),
            $outputDirectory,
        );

        $useCase->execute('sub-1');

        self::assertSame(0, $recorder->calls);
        self::assertCount(1, $this->mailer->sent);
        self::assertTrue($submission->pdfWasEmailed());
        self::assertSame($outputPath, $this->mailer->sent[0]['absolutePdfPath']);
    }

    private function useCase(
        QuestionnaireSubmission $submission,
        PdfGeneratorInterface $generator,
        string $templatesDirectory,
        string $outputDirectory,
        ?SubmissionPdfMailer $mailer = null,
    ): GenerateSubmissionPdf {
        $this->submissions = InMemorySubmissionRepository::with($submission);
        $this->mailer = $mailer instanceof RecordingSubmissionPdfMailer
            ? $mailer
            : new RecordingSubmissionPdfMailer();

        return new GenerateSubmissionPdf(
            $this->submissions,
            InMemoryQuestionnaireRepository::with($submission->questionnaire()),
            new CalculateSubmission($this->submissions, [new Form1040NrCalculator()]),
            $generator,
            new QuestionVisibilityEvaluator(),
            new LocalFileStorage(),
            $this->emailer($this->submissions, $outputDirectory, $mailer ?? $this->mailer),
            $templatesDirectory,
            $outputDirectory,
        );
    }

    private function emailer(
        InMemorySubmissionRepository $submissions,
        string $outputDirectory,
        SubmissionPdfMailer $mailer,
    ): EmailSubmissionPdf {
        return new EmailSubmissionPdf(
            $submissions,
            new LocalFileStorage(),
            $mailer,
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
            $firstName->key(),
            new PdfCoordinates(1, 20.5, 40.25, 11),
        ));
        $questionnaire->addMapping(QuestionMapping::forQuestion(
            'map-spouse',
            $spouseName->key(),
            new PdfCoordinates(1, 20.5, 50.0, 11),
        ));
        $questionnaire->addMapping(QuestionMapping::forQuestion(
            'map-types',
            $incomeTypes->key(),
            new PdfCoordinates(1, 15.0, 60.0),
        ));
        $questionnaire->addMapping(QuestionMapping::forQuestion(
            'map-wages',
            $wages->key(),
            new PdfCoordinates(1, 188.0, 143.1, 9),
        ));
        $questionnaire->addMapping(QuestionMapping::forQuestion(
            'map-notes',
            $notes->key(),
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
