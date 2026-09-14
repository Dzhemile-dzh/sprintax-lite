<?php

declare(strict_types=1);

namespace App\Tests\Application\Questionnaire;

use App\Application\Questionnaire\Update\UpdateQuestionnaire;
use App\Domain\Questionnaire\Entity\Questionnaire;
use App\Domain\Questionnaire\Exception\InvalidQuestionnaire;
use App\Domain\Questionnaire\Exception\QuestionnaireNotFound;
use App\Domain\Questionnaire\Repository\QuestionnaireRepositoryInterface;
use App\Domain\Questionnaire\ValueObject\FormType;
use App\Domain\Questionnaire\ValueObject\QuestionType;
use App\Domain\Submission\Entity\QuestionnaireSubmission;
use App\Domain\User\Entity\User;
use App\Domain\User\ValueObject\Email;
use App\Tests\Support\InMemorySubmissionRepository;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class UpdateQuestionnaireTest extends TestCase
{
    public function testItRenamesWithoutChangingFormType(): void
    {
        $questionnaire = Questionnaire::create('q-1', '1040-NR', FormType::Form1040Nr, 'Original');
        $useCase = new UpdateQuestionnaire(
            new InMemoryQuestionnaireRepository(['q-1' => $questionnaire]),
            new InMemorySubmissionRepository(),
        );

        $useCase->execute('q-1', '1040-NR Demo', 'Updated copy', FormType::Form1040Nr);

        self::assertSame('1040-NR Demo', $questionnaire->name());
        self::assertSame('Updated copy', $questionnaire->description());
        self::assertSame(FormType::Form1040Nr, $questionnaire->formType());
    }

    public function testItAllowsChangingFormTypeBeforeAnySubmission(): void
    {
        $questionnaire = Questionnaire::create('q-1', 'Draft', FormType::Form1040Nr);
        $useCase = new UpdateQuestionnaire(
            new InMemoryQuestionnaireRepository(['q-1' => $questionnaire]),
            new InMemorySubmissionRepository(),
        );

        $useCase->execute('q-1', 'Draft', null, FormType::FormW8Ben);

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
        $useCase = new UpdateQuestionnaire(
            new InMemoryQuestionnaireRepository(['q-1' => $questionnaire]),
            InMemorySubmissionRepository::with($submission),
        );

        try {
            $useCase->execute('q-1', '1040-NR Demo', null, FormType::FormW8Ben);
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
        $useCase = new UpdateQuestionnaire(
            new InMemoryQuestionnaireRepository(['q-1' => $questionnaire]),
            InMemorySubmissionRepository::with($submission),
        );

        $useCase->execute('q-1', '1040-NR Demo', 'Live', FormType::Form1040Nr);

        self::assertSame('1040-NR Demo', $questionnaire->name());
        self::assertSame(FormType::Form1040Nr, $questionnaire->formType());
    }
}

final class InMemoryQuestionnaireRepository implements QuestionnaireRepositoryInterface
{
    /**
     * @param array<string, Questionnaire> $items
     */
    public function __construct(
        private array $items = [],
    ) {
    }

    /**
     * @return list<Questionnaire>
     */
    public function all(): array
    {
        return array_values($this->items);
    }

    public function get(string $id): Questionnaire
    {
        $questionnaire = $this->items[$id] ?? null;

        if (!$questionnaire instanceof Questionnaire) {
            throw QuestionnaireNotFound::withId($id);
        }

        return $questionnaire;
    }

    public function save(Questionnaire $questionnaire): void
    {
        $this->items[$questionnaire->id()] = $questionnaire;
    }
}
