<?php

declare(strict_types=1);

namespace App\Tests\Application\Submission;

use App\Application\Submission\Review\ReviewSubmission;
use App\Domain\Questionnaire\Entity\Questionnaire;
use App\Domain\Questionnaire\QuestionVisibilityEvaluator;
use App\Domain\Questionnaire\ValueObject\FormType;
use App\Domain\Questionnaire\ValueObject\QuestionType;
use App\Domain\Questionnaire\ValueObject\QuestionValidation;
use App\Domain\Submission\Entity\QuestionnaireSubmission;
use App\Domain\Submission\QuestionAnswerValidator;
use App\Domain\Submission\SubmissionCompleteness;
use App\Domain\Submission\ValueObject\AnswerValue;
use App\Domain\User\Entity\User;
use App\Domain\User\ValueObject\Email;
use App\Tests\Support\InMemorySubmissionRepository;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ReviewSubmissionTest extends TestCase
{
    public function testAnIncompleteInProgressSubmissionMustResumeTheCurrentStep(): void
    {
        $review = $this->useCase($this->submission())->execute('sub-1');

        self::assertSame('step-1', $review->resumeStepId);
        self::assertFalse($review->canFinalize);
        self::assertSame('Personal', $review->steps[0]['step']->title());
    }

    public function testACompleteInProgressSubmissionCanBeFinalized(): void
    {
        $submission = $this->submission();
        $name = $submission->questionnaire()->findQuestionByKey('first_name');
        self::assertNotNull($name);
        $submission->recordAnswer($name, AnswerValue::text('Ada'), new DateTimeImmutable('2026-01-01T10:05:00+00:00'));

        $review = $this->useCase($submission)->execute('sub-1');

        self::assertNull($review->resumeStepId);
        self::assertTrue($review->canFinalize);
        self::assertSame('Ada', $review->steps[0]['questions'][0]['answer']?->raw());
    }

    private function useCase(QuestionnaireSubmission $submission): ReviewSubmission
    {
        $visibility = new QuestionVisibilityEvaluator();

        return new ReviewSubmission(
            InMemorySubmissionRepository::with($submission),
            $visibility,
            new SubmissionCompleteness($visibility, new QuestionAnswerValidator()),
        );
    }

    private function submission(): QuestionnaireSubmission
    {
        $questionnaire = Questionnaire::create('q-1', '1040-NR', FormType::Form1040Nr);
        $questionnaire->addStep('step-1', 'Personal');
        $questionnaire->addQuestion(
            'step-1',
            'q-name',
            'first_name',
            'First name',
            QuestionType::ShortText,
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
