<?php

declare(strict_types=1);

namespace App\Tests\Application\Submission;

use App\Application\Submission\NextStep\DetermineNextStep;
use App\Application\Submission\SaveStep\SaveStep;
use App\Domain\Questionnaire\Entity\Questionnaire;
use App\Domain\Questionnaire\QuestionVisibilityEvaluator;
use App\Domain\Questionnaire\ValueObject\FormType;
use App\Domain\Questionnaire\ValueObject\QuestionType;
use App\Domain\Questionnaire\ValueObject\QuestionValidation;
use App\Domain\Questionnaire\ValueObject\VisibilityCondition;
use App\Domain\Questionnaire\ValueObject\VisibilityOperator;
use App\Domain\Questionnaire\ValueObject\VisibilityRule;
use App\Domain\Submission\Entity\QuestionnaireSubmission;
use App\Domain\Submission\Exception\InvalidSubmission;
use App\Domain\Submission\Exception\SubmissionNotFound;
use App\Domain\Submission\QuestionAnswerValidator;
use App\Domain\Submission\Repository\SubmissionRepositoryInterface;
use App\Domain\User\Entity\User;
use App\Domain\User\ValueObject\Email;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SaveStepTest extends TestCase
{
    public function testItRecordsAVisibleAnswerAndIgnoresAHiddenPostedAnswer(): void
    {
        $submission = $this->submission();
        $this->saveStep($submission)->execute('sub-1', 'step-1', [
            'married' => 'no',
            'spouse_name' => 'Hacker',
        ], false);

        $answers = $submission->answersByQuestionKey();
        self::assertSame('no', $answers['married']->raw());
        self::assertArrayNotHasKey('spouse_name', $answers);
    }

    public function testItClearsAPreviousAnswerWhenTheQuestionBecomesHidden(): void
    {
        $submission = $this->submission();
        $save = $this->saveStep($submission);
        $save->execute('sub-1', 'step-1', [
            'married' => 'yes',
            'spouse_name' => 'Ada',
        ], false);
        $save->execute('sub-1', 'step-1', [
            'married' => 'no',
        ], false);

        $answers = $submission->answersByQuestionKey();
        self::assertSame('no', $answers['married']->raw());
        self::assertArrayNotHasKey('spouse_name', $answers);
    }

    public function testItStaysOnTheStepWhenANewlyVisibleQuestionWasNotSubmitted(): void
    {
        $submission = $this->submission();
        $next = $this->saveStep($submission)->execute('sub-1', 'step-1', [
            'married' => 'yes',
        ], true);

        self::assertSame('step-1', $next);
        self::assertSame('yes', $submission->answersByQuestionKey()['married']->raw());
        self::assertArrayNotHasKey('spouse_name', $submission->answersByQuestionKey());
        self::assertSame('step-1', $submission->currentStepId());
    }

    public function testItRejectsAMissingRequiredAnswerWhenAdvancing(): void
    {
        $submission = $this->submission();

        $this->expectException(InvalidSubmission::class);
        $this->expectExceptionMessage('Question "married" is required.');
        $this->saveStep($submission)->execute('sub-1', 'step-1', [
            'married' => '',
        ], true);
    }

    public function testItRejectsSkippingAheadToALaterStep(): void
    {
        $questionnaire = Questionnaire::create('q-1', '1040-NR', FormType::Form1040Nr);
        $questionnaire->addStep('step-1', 'Personal');
        $questionnaire->addStep('step-2', 'Income');
        $questionnaire->addQuestion(
            'step-1',
            'q-name',
            'first_name',
            'First name',
            QuestionType::ShortText,
            null,
            QuestionValidation::required(),
        );
        $questionnaire->addQuestion(
            'step-2',
            'q-wages',
            'wages',
            'Wages',
            QuestionType::Number,
            null,
            QuestionValidation::required(),
        );
        $submission = QuestionnaireSubmission::start(
            'sub-1',
            $questionnaire,
            User::registerClient('user-1', new Email('client@example.test'), 'hashed-password'),
            new DateTimeImmutable('2026-01-01T10:00:00+00:00'),
        );

        try {
            $this->saveStep($submission)->execute('sub-1', 'step-2', [
                'wages' => '50000',
            ], true);
            self::fail('Expected skipping ahead to be rejected.');
        } catch (InvalidSubmission $exception) {
            self::assertTrue($exception->deniesAccess());
            self::assertSame('Cannot skip ahead to a later wizard step.', $exception->getMessage());
            self::assertSame('step-1', $submission->currentStepId());
            self::assertArrayNotHasKey('wages', $submission->answersByQuestionKey());
        }
    }

    public function testItRejectsAnInvalidDate(): void
    {
        $questionnaire = Questionnaire::create('q-1', '1040-NR', FormType::Form1040Nr);
        $questionnaire->addStep('step-1', 'Personal');
        $questionnaire->addQuestion(
            'step-1',
            'q-birth',
            'birth_date',
            'Birth date',
            QuestionType::Date,
            null,
            QuestionValidation::required(),
        );
        $submission = QuestionnaireSubmission::start(
            'sub-1',
            $questionnaire,
            User::registerClient('user-1', new Email('client@example.test'), 'hashed-password'),
            new DateTimeImmutable('2026-01-01T10:00:00+00:00'),
        );

        $this->expectException(InvalidSubmission::class);
        $this->expectExceptionMessage('must be a valid date');
        $this->saveStep($submission)->execute('sub-1', 'step-1', [
            'birth_date' => '13/13/1990',
        ], true);
    }

    private function saveStep(QuestionnaireSubmission $submission): SaveStep
    {
        return new SaveStep(
            new InMemorySubmissionRepository(['sub-1' => $submission]),
            new QuestionVisibilityEvaluator(),
            new QuestionAnswerValidator(),
            new DetermineNextStep(),
        );
    }

    private function submission(): QuestionnaireSubmission
    {
        $questionnaire = Questionnaire::create('q-1', '1040-NR', FormType::Form1040Nr);
        $questionnaire->addStep('step-1', 'Personal');
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
            QuestionValidation::required(),
            new VisibilityRule([
                new VisibilityCondition('married', VisibilityOperator::Equals, 'yes'),
            ]),
        );

        return QuestionnaireSubmission::start(
            'sub-1',
            $questionnaire,
            User::registerClient('user-1', new Email('client@example.test'), 'hashed-password'),
            new DateTimeImmutable('2026-01-01T10:00:00+00:00'),
        );
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

    public function findByUserAndQuestionnaire(string $userId, string $questionnaireId): ?QuestionnaireSubmission
    {
        foreach ($this->submissions as $submission) {
            if ($submission->user()->id() === $userId && $submission->questionnaire()->id() === $questionnaireId) {
                return $submission;
            }
        }

        return null;
    }

    /**
     * @return list<QuestionnaireSubmission>
     */
    public function findForUser(string $userId): array
    {
        $matches = [];

        foreach ($this->submissions as $submission) {
            if ($submission->user()->id() === $userId) {
                $matches[] = $submission;
            }
        }

        return $matches;
    }

    /**
     * @return list<QuestionnaireSubmission>
     */
    public function findAllRecent(): array
    {
        return array_values($this->submissions);
    }

    public function existsForQuestionnaire(string $questionnaireId): bool
    {
        foreach ($this->submissions as $submission) {
            if ($submission->questionnaire()->id() === $questionnaireId) {
                return true;
            }
        }

        return false;
    }

    public function save(QuestionnaireSubmission $submission): void
    {
        $this->submissions[$submission->id()] = $submission;
    }
}
