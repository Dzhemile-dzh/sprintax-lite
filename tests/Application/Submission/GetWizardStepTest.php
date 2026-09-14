<?php

declare(strict_types=1);

namespace App\Tests\Application\Submission;

use App\Application\Submission\GetWizardStep\GetWizardStep;
use App\Domain\Questionnaire\Entity\Questionnaire;
use App\Domain\Questionnaire\QuestionVisibilityEvaluator;
use App\Domain\Questionnaire\ValueObject\FormType;
use App\Domain\Questionnaire\ValueObject\QuestionType;
use App\Domain\Questionnaire\ValueObject\QuestionValidation;
use App\Domain\Submission\Entity\QuestionnaireSubmission;
use App\Domain\Submission\Exception\InvalidSubmission;
use App\Domain\User\Entity\User;
use App\Domain\User\ValueObject\Email;
use App\Tests\Support\InMemorySubmissionRepository;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class GetWizardStepTest extends TestCase
{
    public function testItReturnsVisibleQuestionsAndThePreviousStep(): void
    {
        $submission = $this->twoStepSubmission();
        $submission->moveToStep('step-2', new DateTimeImmutable('2026-01-01T10:05:00+00:00'));
        $view = $this->useCase($submission)->execute('sub-1', 'step-2');

        self::assertSame($submission, $view->submission);
        self::assertTrue($view->openForEditing);
        self::assertSame('step-2', $view->editableStep()->id());
        self::assertSame(['wages'], array_map(static fn ($question): string => $question->key(), $view->visibleQuestions));
        self::assertSame('step-1', $view->previousStepId);
    }

    public function testItAllowsOpeningAnEarlierStep(): void
    {
        $submission = $this->twoStepSubmission();
        $submission->moveToStep('step-2', new DateTimeImmutable('2026-01-01T10:05:00+00:00'));
        $view = $this->useCase($submission)->execute('sub-1', 'step-1');

        self::assertTrue($view->openForEditing);
        self::assertSame('step-1', $view->editableStep()->id());
        self::assertNull($view->previousStepId);
    }

    public function testItRejectsSkippingAhead(): void
    {
        $submission = $this->twoStepSubmission();

        try {
            $this->useCase($submission)->execute('sub-1', 'step-2');
            self::fail('Expected skipping ahead to be rejected.');
        } catch (InvalidSubmission $exception) {
            self::assertTrue($exception->deniesAccess());
            self::assertSame('Cannot skip ahead to a later wizard step.', $exception->getMessage());
        }
    }

    public function testItRejectsAnUnknownStep(): void
    {
        $this->expectException(InvalidSubmission::class);
        $this->expectExceptionMessage('Step "missing" is not part of this submission questionnaire.');
        $this->useCase($this->twoStepSubmission())->execute('sub-1', 'missing');
    }

    public function testItSendsAFinalizedSubmissionBackToReview(): void
    {
        $submission = $this->twoStepSubmission();
        $submission->finalize(new DateTimeImmutable('2026-01-01T12:00:00+00:00'));
        $view = $this->useCase($submission)->execute('sub-1', 'step-1');

        self::assertFalse($view->openForEditing);
        self::assertNull($view->step);
        self::assertSame([], $view->visibleQuestions);
        self::assertNull($view->previousStepId);
    }

    private function useCase(QuestionnaireSubmission $submission): GetWizardStep
    {
        return new GetWizardStep(
            InMemorySubmissionRepository::with($submission),
            new QuestionVisibilityEvaluator(),
        );
    }

    private function twoStepSubmission(): QuestionnaireSubmission
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

        return QuestionnaireSubmission::start(
            'sub-1',
            $questionnaire,
            User::registerClient('user-1', new Email('client@example.test'), 'hashed-password'),
            new DateTimeImmutable('2026-01-01T10:00:00+00:00'),
        );
    }
}
