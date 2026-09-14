<?php

declare(strict_types=1);

namespace App\Tests\Application\Calculation;

use App\Application\Calculation\CalculateSubmission;
use App\Domain\Calculation\Contract\CalculatorInterface;
use App\Domain\Calculation\DTO\CalculationInput;
use App\Domain\Calculation\DTO\CalculationResult;
use App\Domain\Calculation\Exception\UnsupportedFormType;
use App\Domain\Questionnaire\Entity\Questionnaire;
use App\Domain\Questionnaire\ValueObject\QuestionType;
use App\Domain\Submission\Entity\QuestionnaireSubmission;
use App\Domain\Submission\Exception\SubmissionNotFound;
use App\Domain\Submission\Repository\SubmissionRepositoryInterface;
use App\Domain\Submission\ValueObject\AnswerValue;
use App\Domain\User\Entity\User;
use App\Domain\User\ValueObject\Email;
use App\Infrastructure\Calculation\Form1040NrCalculator;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class CalculateSubmissionTest extends TestCase
{
    public function testItSelectsTheCalculatorThatSupportsTheQuestionnaireForm(): void
    {
        $submission = $this->submissionWithWages('1040-NR', '50000');
        $useCase = new CalculateSubmission(
            new InMemorySubmissionRepository(['sub-1' => $submission]),
            [
                new MatchingCalculator('W-2', ['ignored' => 1]),
                new Form1040NrCalculator(),
            ],
        );

        $result = $useCase->execute('sub-1');

        self::assertSame(50000.0, $result->value(Form1040NrCalculator::FIELD_TOTAL_INCOME));
        self::assertSame(5000.0, $result->value(Form1040NrCalculator::FIELD_TAX_OWED));
    }

    public function testItFailsWhenNoCalculatorSupportsTheForm(): void
    {
        $submission = $this->submissionWithWages('W-8BEN', '1000');
        $useCase = new CalculateSubmission(
            new InMemorySubmissionRepository(['sub-1' => $submission]),
            [new Form1040NrCalculator()],
        );

        $this->expectException(UnsupportedFormType::class);
        $useCase->execute('sub-1');
    }

    private function submissionWithWages(string $formName, string $wages): QuestionnaireSubmission
    {
        $questionnaire = Questionnaire::create('q-1', $formName);
        $questionnaire->addStep('step-1', 'Income');
        $wagesQuestion = $questionnaire->addQuestion(
            'step-1',
            'q-wages',
            'income_wages',
            'Wages',
            QuestionType::Number,
        );

        $submission = QuestionnaireSubmission::start(
            'sub-1',
            $questionnaire,
            User::registerClient('user-1', new Email('client@example.test'), 'hashed-password'),
            new DateTimeImmutable('2026-01-01T10:00:00+00:00'),
        );
        $submission->recordAnswer(
            $wagesQuestion,
            AnswerValue::text($wages),
            new DateTimeImmutable('2026-01-01T10:05:00+00:00'),
        );

        return $submission;
    }
}

final class InMemorySubmissionRepository implements SubmissionRepositoryInterface
{
    /**
     * @param array<string, QuestionnaireSubmission> $submissions
     */
    public function __construct(
        private array $submissions,
    ) {
    }

    public function get(string $id): QuestionnaireSubmission
    {
        $submission = $this->submissions[$id] ?? null;

        if (!$submission instanceof QuestionnaireSubmission) {
            throw SubmissionNotFound::withId($id);
        }

        return $submission;
    }

    public function save(QuestionnaireSubmission $submission): void
    {
        $this->submissions[$submission->id()] = $submission;
    }
}

final class MatchingCalculator implements CalculatorInterface
{
    /**
     * @param array<string, int|float|string> $values
     */
    public function __construct(
        private readonly string $formType,
        private readonly array $values,
    ) {
    }

    public function supports(string $formType): bool
    {
        return $formType === $this->formType;
    }

    public function calculate(CalculationInput $input): CalculationResult
    {
        return new CalculationResult($this->values);
    }
}
