<?php

declare(strict_types=1);

namespace App\Tests\Application\Questionnaire;

use App\Application\Questionnaire\Get\GetQuestionnaire;
use App\Domain\Questionnaire\Entity\Questionnaire;
use App\Domain\Questionnaire\ValueObject\FormType;
use App\Domain\Questionnaire\ValueObject\QuestionType;
use App\Domain\Submission\Entity\QuestionnaireSubmission;
use App\Domain\User\Entity\User;
use App\Domain\User\ValueObject\Email;
use App\Tests\Support\InMemoryQuestionnaireRepository;
use App\Tests\Support\InMemorySubmissionRepository;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class GetQuestionnaireTest extends TestCase
{
    public function testItReportsFormTypeUnlockedWhenNoSubmissionExists(): void
    {
        $questionnaire = Questionnaire::create('q-1', '1040-NR', FormType::Form1040Nr);
        $loaded = $this->useCase($questionnaire)->execute('q-1');

        self::assertSame($questionnaire, $loaded->questionnaire);
        self::assertFalse($loaded->formTypeLocked);
    }

    public function testItReportsFormTypeLockedAfterAClientStarts(): void
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
        $loaded = $this->useCase($questionnaire, $submission)->execute('q-1');

        self::assertTrue($loaded->formTypeLocked);
    }

    private function useCase(
        Questionnaire $questionnaire,
        ?QuestionnaireSubmission $submission = null,
    ): GetQuestionnaire {
        return new GetQuestionnaire(
            InMemoryQuestionnaireRepository::with($questionnaire),
            $submission === null
                ? new InMemorySubmissionRepository()
                : InMemorySubmissionRepository::with($submission),
        );
    }
}
