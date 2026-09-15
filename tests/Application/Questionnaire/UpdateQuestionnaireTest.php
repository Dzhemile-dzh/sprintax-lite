<?php

declare(strict_types=1);

namespace App\Tests\Application\Questionnaire;

use App\Application\Questionnaire\WriteQuestionnaire;
use App\Domain\Questionnaire\Entity\Questionnaire;
use App\Domain\Questionnaire\Exception\InvalidQuestionnaire;
use App\Domain\Questionnaire\ValueObject\FormType;
use App\Domain\Questionnaire\ValueObject\QuestionType;
use App\Domain\Submission\Entity\QuestionnaireSubmission;
use App\Domain\User\Entity\User;
use App\Domain\User\ValueObject\Email;
use App\Tests\Support\InMemoryQuestionnaireRepository;
use App\Tests\Support\InMemorySubmissionRepository;
use App\Tests\Support\TestRevisionRecorder;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class UpdateQuestionnaireTest extends TestCase
{
    public function testItRenamesWithoutChangingFormType(): void
    {
        $questionnaire = Questionnaire::create('q-1', '1040-NR', FormType::Form1040Nr, 'Original');
        $useCase = new WriteQuestionnaire(
            new InMemoryQuestionnaireRepository(['q-1' => $questionnaire]),
            new InMemorySubmissionRepository(),
            TestRevisionRecorder::create(),
        );

        $useCase->update('q-1', '1040-NR Demo', 'Updated copy', FormType::Form1040Nr);

        self::assertSame('1040-NR Demo', $questionnaire->name());
        self::assertSame('Updated copy', $questionnaire->description());
        self::assertSame(FormType::Form1040Nr, $questionnaire->formType());
    }

    public function testItAllowsChangingFormTypeBeforeAnySubmission(): void
    {
        $questionnaire = Questionnaire::create('q-1', 'Draft', FormType::Form1040Nr);
        $useCase = new WriteQuestionnaire(
            new InMemoryQuestionnaireRepository(['q-1' => $questionnaire]),
            new InMemorySubmissionRepository(),
            TestRevisionRecorder::create(),
        );

        $useCase->update('q-1', 'Draft', null, FormType::FormW8Ben);

        self::assertSame(FormType::FormW8Ben, $questionnaire->formType());
    }

    public function testItRejectsChangingFormTypeAfterASubmissionExists(): void
    {
        $questionnaire = Questionnaire::create('q-1', '1040-NR', FormType::Form1040Nr);
        $questionnaire->addStep('step-1', 'Personal');
        $questionnaire->addQuestion('step-1', 'q-name', 'first_name', 'First name', QuestionType::ShortText);
        $submission = QuestionnaireSubmission::start(
            'sub-1',
            $questionnaire,
            User::registerClient('user-1', new Email('client@example.test'), 'hashed-password'),
            new DateTimeImmutable('2026-01-01T10:00:00+00:00'),
        );
        $useCase = new WriteQuestionnaire(
            new InMemoryQuestionnaireRepository(['q-1' => $questionnaire]),
            InMemorySubmissionRepository::with($submission),
            TestRevisionRecorder::create(),
        );

        try {
            $useCase->update('q-1', '1040-NR Demo', null, FormType::FormW8Ben);
            self::fail('Expected form type to stay locked after a submission exists.');
        } catch (InvalidQuestionnaire $exception) {
            self::assertSame(
                'Form type cannot be changed after a client has started this questionnaire.',
                $exception->getMessage(),
            );
            self::assertSame(FormType::Form1040Nr, $questionnaire->formType());
            self::assertSame('1040-NR', $questionnaire->name());
        }
    }

    public function testItStillAllowsRenameAfterASubmissionExists(): void
    {
        $questionnaire = Questionnaire::create('q-1', '1040-NR', FormType::Form1040Nr);
        $questionnaire->addStep('step-1', 'Personal');
        $questionnaire->addQuestion('step-1', 'q-name', 'first_name', 'First name', QuestionType::ShortText);
        $submission = QuestionnaireSubmission::start(
            'sub-1',
            $questionnaire,
            User::registerClient('user-1', new Email('client@example.test'), 'hashed-password'),
            new DateTimeImmutable('2026-01-01T10:00:00+00:00'),
        );
        $useCase = new WriteQuestionnaire(
            new InMemoryQuestionnaireRepository(['q-1' => $questionnaire]),
            InMemorySubmissionRepository::with($submission),
            TestRevisionRecorder::create(),
        );

        $useCase->update('q-1', '1040-NR Demo', 'Live', FormType::Form1040Nr);

        self::assertSame('1040-NR Demo', $questionnaire->name());
        self::assertSame(FormType::Form1040Nr, $questionnaire->formType());
    }
}
