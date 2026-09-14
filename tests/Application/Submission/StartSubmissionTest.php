<?php

declare(strict_types=1);

namespace App\Tests\Application\Submission;

use App\Application\Submission\Start\StartSubmission;
use App\Domain\Questionnaire\Entity\Questionnaire;
use App\Domain\Questionnaire\Exception\QuestionnaireNotFound;
use App\Domain\Questionnaire\Repository\QuestionnaireRepositoryInterface;
use App\Domain\User\Entity\User;
use App\Domain\User\Exception\UserNotFound;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\ValueObject\Email;
use App\Tests\Support\InMemorySubmissionRepository;
use PHPUnit\Framework\TestCase;

final class StartSubmissionTest extends TestCase
{
    public function testASecondStartResumesTheExistingSubmission(): void
    {
        $user = User::registerClient('user-1', new Email('client@example.test'), 'hashed-password');
        $questionnaire = Questionnaire::create('q-1', '1040-NR');
        $questionnaire->addStep('step-1', 'Personal');
        $submissions = new InMemorySubmissionRepository();
        $useCase = new StartSubmission(
            $submissions,
            new InMemoryQuestionnaireRepository([$questionnaire]),
            new InMemoryStartUserRepository([$user]),
        );

        $first = $useCase->execute($user->id(), $questionnaire->id());
        $second = $useCase->execute($user->id(), $questionnaire->id());

        self::assertSame($first->id(), $second->id());
        self::assertSame(1, $submissions->saveCount);
        self::assertSame([$first], $submissions->findForUser($user->id()));
    }
}

/**
 * @internal
 */
final class InMemoryQuestionnaireRepository implements QuestionnaireRepositoryInterface
{
    /**
     * @param list<Questionnaire> $questionnaires
     */
    public function __construct(
        private array $questionnaires,
    ) {
    }

    public function all(): array
    {
        return $this->questionnaires;
    }

    public function get(string $id): Questionnaire
    {
        foreach ($this->questionnaires as $questionnaire) {
            if ($questionnaire->id() === $id) {
                return $questionnaire;
            }
        }

        throw QuestionnaireNotFound::withId($id);
    }

    public function save(Questionnaire $questionnaire): void
    {
        $this->questionnaires[] = $questionnaire;
    }
}

/**
 * @internal
 */
final class InMemoryStartUserRepository implements UserRepositoryInterface
{
    /**
     * @param list<User> $users
     */
    public function __construct(
        private array $users,
    ) {
    }

    public function get(string $id): User
    {
        foreach ($this->users as $user) {
            if ($user->id() === $id) {
                return $user;
            }
        }

        throw UserNotFound::withId($id);
    }

    public function findByEmail(Email $email): ?User
    {
        foreach ($this->users as $user) {
            if ($user->email()->value() === $email->value()) {
                return $user;
            }
        }

        return null;
    }

    public function save(User $user): void
    {
        $this->users[] = $user;
    }
}
