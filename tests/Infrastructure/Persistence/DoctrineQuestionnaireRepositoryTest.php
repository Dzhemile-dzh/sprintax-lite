<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Persistence;

use App\Domain\Questionnaire\Entity\QuestionMapping;
use App\Domain\Questionnaire\Entity\QuestionOption;
use App\Domain\Questionnaire\Entity\Questionnaire;
use App\Domain\Questionnaire\Exception\QuestionnaireNotFound;
use App\Domain\Questionnaire\Repository\QuestionnaireRepositoryInterface;
use App\Domain\Questionnaire\ValueObject\FormType;
use App\Domain\Questionnaire\ValueObject\PdfCoordinates;
use App\Domain\Questionnaire\ValueObject\QuestionType;
use App\Domain\Questionnaire\ValueObject\QuestionValidation;
use App\Domain\Questionnaire\ValueObject\VisibilityCondition;
use App\Domain\Questionnaire\ValueObject\VisibilityOperator;
use App\Domain\Questionnaire\ValueObject\VisibilityRule;
use App\Domain\Submission\Entity\QuestionnaireSubmission;
use App\Domain\Submission\Exception\SubmissionNotFound;
use App\Domain\Submission\Repository\SubmissionRepositoryInterface;
use App\Domain\Submission\ValueObject\AnswerValue;
use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\ValueObject\Email;
use DateTimeImmutable;

final class DoctrineQuestionnaireRepositoryTest extends DatabaseTestCase
{
    public function testItPersistsAndReloadsAQuestionnaireAggregate(): void
    {
        $questionnaire = Questionnaire::create('q-1', '1040-NR', FormType::Form1040Nr, 'Demo');
        $questionnaire->addStep('step-1', 'Personal');
        $questionnaire->addQuestion('step-1', 'question-1', 'married', 'Married?', QuestionType::YesNo);
        $spouseName = $questionnaire->addQuestion(
            'step-1',
            'question-2',
            'spouse_name',
            "Spouse's name",
            QuestionType::ShortText,
            validation: QuestionValidation::required(),
            visibility: new VisibilityRule([
                new VisibilityCondition('married', VisibilityOperator::Equals, 'yes'),
            ]),
        );
        $status = $questionnaire->addQuestion(
            'step-1',
            'question-3',
            'status',
            'Status',
            QuestionType::SingleChoice,
        );
        $status->addOption(QuestionOption::create('opt-1', 'Single', 'single', 1));
        $questionnaire->addMapping(QuestionMapping::forQuestion(
            'map-1',
            'married',
            new PdfCoordinates(1, 20.5, 40.0, 10),
        ));

        $questionnaires = self::getContainer()->get(QuestionnaireRepositoryInterface::class);
        self::assertInstanceOf(QuestionnaireRepositoryInterface::class, $questionnaires);
        $questionnaires->save($questionnaire);

        $this->entityManager->clear();

        $reloaded = $questionnaires->get('q-1');

        self::assertSame('1040-NR', $reloaded->name());
        self::assertSame(FormType::Form1040Nr, $reloaded->formType());
        self::assertCount(1, $reloaded->steps());
        self::assertSame('spouse_name', $spouseName->key());

        $spouse = $reloaded->findQuestionByKey('spouse_name');
        self::assertNotNull($spouse);
        self::assertSame('married', $spouse->visibility()->conditions[0]->questionKey);
        self::assertTrue($spouse->validation()->required);

        $statusQuestion = $reloaded->findQuestionByKey('status');
        self::assertNotNull($statusQuestion);
        self::assertCount(1, $statusQuestion->options());
        self::assertSame('single', $statusQuestion->options()[0]->value());
        self::assertCount(1, $reloaded->mappings());
        self::assertSame(20.5, $reloaded->mappings()[0]->coordinates()->xMm);
    }

    public function testItPersistsASubmissionWithAnswers(): void
    {
        $questionnaire = Questionnaire::create('q-1', '1040-NR', FormType::Form1040Nr);
        $questionnaire->addStep('step-1', 'Personal');
        $married = $questionnaire->addQuestion('step-1', 'question-1', 'married', 'Married?', QuestionType::YesNo);

        $user = User::registerClient('user-1', new Email('client@example.test'), 'hashed-password');

        $questionnaires = self::getContainer()->get(QuestionnaireRepositoryInterface::class);
        $users = self::getContainer()->get(UserRepositoryInterface::class);
        $submissions = self::getContainer()->get(SubmissionRepositoryInterface::class);
        self::assertInstanceOf(QuestionnaireRepositoryInterface::class, $questionnaires);
        self::assertInstanceOf(UserRepositoryInterface::class, $users);
        self::assertInstanceOf(SubmissionRepositoryInterface::class, $submissions);

        $questionnaires->save($questionnaire);
        $users->save($user);

        $submission = QuestionnaireSubmission::start(
            'sub-1',
            $questionnaire,
            $user,
            new DateTimeImmutable('2026-01-01T10:00:00+00:00'),
        );
        $submission->recordAnswer($married, AnswerValue::text('yes'), new DateTimeImmutable('2026-01-01T10:05:00+00:00'));
        $submissions->save($submission);

        $this->entityManager->clear();

        $reloaded = $submissions->get('sub-1');

        self::assertSame('user-1', $reloaded->user()->id());
        self::assertSame('step-1', $reloaded->currentStepId());
        self::assertSame('yes', $reloaded->answerFor('question-1')?->value()->raw());
        self::assertSame('2026-01-01T10:00:00+00:00', $reloaded->createdAt()->format(DATE_ATOM));
        self::assertSame('2026-01-01T10:05:00+00:00', $reloaded->updatedAt()->format(DATE_ATOM));
    }

    public function testLoadedSubmissionCanReadPdfMappingsFromTheQuestionnaire(): void
    {
        $questionnaire = Questionnaire::create('q-1', '1040-NR', FormType::Form1040Nr);
        $questionnaire->addStep('step-1', 'Personal');
        $questionnaire->addQuestion('step-1', 'question-1', 'last_name', 'Last name', QuestionType::ShortText);
        $questionnaire->addMapping(QuestionMapping::forQuestion(
            'map-1',
            'last_name',
            new PdfCoordinates(1, 90.0, 40.3, 9),
        ));

        $user = User::registerClient('user-1', new Email('client@example.test'), 'hashed-password');
        $questionnaires = self::getContainer()->get(QuestionnaireRepositoryInterface::class);
        $users = self::getContainer()->get(UserRepositoryInterface::class);
        $submissions = self::getContainer()->get(SubmissionRepositoryInterface::class);
        self::assertInstanceOf(QuestionnaireRepositoryInterface::class, $questionnaires);
        self::assertInstanceOf(UserRepositoryInterface::class, $users);
        self::assertInstanceOf(SubmissionRepositoryInterface::class, $submissions);

        $questionnaires->save($questionnaire);
        $users->save($user);
        $submissions->save(QuestionnaireSubmission::start(
            'sub-1',
            $questionnaire,
            $user,
            new DateTimeImmutable('2026-01-01T10:00:00+00:00'),
        ));

        $this->entityManager->clear();

        $fromSubmission = $submissions->get('sub-1');
        self::assertCount(1, $fromSubmission->questionnaire()->mappings());

        $fromAdmin = $questionnaires->get('q-1');
        self::assertCount(1, $fromAdmin->mappings());
        self::assertSame('last_name', $fromAdmin->mappings()[0]->source()->reference);
        self::assertSame(90.0, $fromAdmin->mappings()[0]->coordinates()->xMm);
    }

    public function testItReplacesAnExistingAnswerOnReload(): void
    {
        $questionnaire = Questionnaire::create('q-1', '1040-NR', FormType::Form1040Nr);
        $questionnaire->addStep('step-1', 'Personal');
        $married = $questionnaire->addQuestion('step-1', 'question-1', 'married', 'Married?', QuestionType::YesNo);
        $user = User::registerClient('user-1', new Email('client@example.test'), 'hashed-password');

        $questionnaires = self::getContainer()->get(QuestionnaireRepositoryInterface::class);
        $users = self::getContainer()->get(UserRepositoryInterface::class);
        $submissions = self::getContainer()->get(SubmissionRepositoryInterface::class);
        self::assertInstanceOf(QuestionnaireRepositoryInterface::class, $questionnaires);
        self::assertInstanceOf(UserRepositoryInterface::class, $users);
        self::assertInstanceOf(SubmissionRepositoryInterface::class, $submissions);

        $questionnaires->save($questionnaire);
        $users->save($user);

        $submission = QuestionnaireSubmission::start(
            'sub-1',
            $questionnaire,
            $user,
            new DateTimeImmutable('2026-01-01T10:00:00+00:00'),
        );
        $submission->recordAnswer($married, AnswerValue::text('yes'), new DateTimeImmutable('2026-01-01T10:05:00+00:00'));
        $submissions->save($submission);

        $this->entityManager->clear();

        $reloaded = $submissions->get('sub-1');
        $reloadedQuestion = $reloaded->questionnaire()->findQuestion('question-1');
        self::assertNotNull($reloadedQuestion);
        $reloaded->recordAnswer($reloadedQuestion, AnswerValue::text('no'), new DateTimeImmutable('2026-01-01T10:06:00+00:00'));
        $submissions->save($reloaded);

        $this->entityManager->clear();

        $updated = $submissions->get('sub-1');
        self::assertCount(1, $updated->answers());
        self::assertSame('no', $updated->answerFor('question-1')?->value()->raw());
    }

    public function testGetThrowsWhenTheQuestionnaireDoesNotExist(): void
    {
        $questionnaires = self::getContainer()->get(QuestionnaireRepositoryInterface::class);
        self::assertInstanceOf(QuestionnaireRepositoryInterface::class, $questionnaires);

        $this->expectException(QuestionnaireNotFound::class);
        $questionnaires->get('missing');
    }

    public function testGetThrowsWhenTheSubmissionDoesNotExist(): void
    {
        $submissions = self::getContainer()->get(SubmissionRepositoryInterface::class);
        self::assertInstanceOf(SubmissionRepositoryInterface::class, $submissions);

        $this->expectException(SubmissionNotFound::class);
        $submissions->get('missing');
    }
}
