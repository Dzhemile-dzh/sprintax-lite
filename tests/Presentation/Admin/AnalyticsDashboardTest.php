<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Admin;

use App\Application\User\PasswordHasherInterface;
use App\Domain\Questionnaire\Entity\Questionnaire;
use App\Domain\Questionnaire\Repository\QuestionnaireRepositoryInterface;
use App\Domain\Questionnaire\ValueObject\FormType;
use App\Domain\Questionnaire\ValueObject\QuestionType;
use App\Domain\Submission\Entity\QuestionnaireSubmission;
use App\Domain\Submission\Repository\SubmissionRepositoryInterface;
use App\Domain\Submission\ValueObject\AnswerValue;
use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\ValueObject\Email;
use App\Infrastructure\Security\SecurityUser;
use App\Tests\Support\WebDatabaseTestCase;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Response;

final class AnalyticsDashboardTest extends WebDatabaseTestCase
{
    public function testAnAdminSeesSubmissionCountsByStatusAndQuestionnaire(): void
    {
        $this->loginAdmin();

        $questionnaire = Questionnaire::create('q-1', '1040-NR', FormType::Form1040Nr);
        $questionnaire->addStep('step-1', 'Personal');
        $question = $questionnaire->addQuestion(
            'step-1',
            'q-name',
            'first_name',
            'First name',
            QuestionType::ShortText,
        );
        $this->questionnaires()->save($questionnaire);

        $alice = User::registerClient('u-alice', new Email('alice@example.test'), $this->hash('password1'));
        $bob = User::registerClient('u-bob', new Email('bob@example.test'), $this->hash('password1'));
        $this->users()->save($alice);
        $this->users()->save($bob);

        $inProgress = QuestionnaireSubmission::start(
            'sub-1',
            $questionnaire,
            $alice,
            new DateTimeImmutable('2026-01-01T10:00:00+00:00'),
        );
        $inProgress->recordAnswer($question, AnswerValue::text('Alice'), new DateTimeImmutable('2026-01-01T10:05:00+00:00'));
        $this->submissions()->save($inProgress);

        $ready = QuestionnaireSubmission::start(
            'sub-2',
            $questionnaire,
            $bob,
            new DateTimeImmutable('2026-01-02T10:00:00+00:00'),
        );
        $ready->finalize(new DateTimeImmutable('2026-01-02T11:00:00+00:00'));
        $ready->markPdfReady(new DateTimeImmutable('2026-01-02T11:05:00+00:00'), 'sub-2.pdf');
        $ready->markPdfEmailed(new DateTimeImmutable('2026-01-02T11:06:00+00:00'));
        $this->submissions()->save($ready);

        $crawler = $this->client->request('GET', '/admin');
        self::assertResponseIsSuccessful();
        $crawler = $this->client->click($crawler->selectLink('Analytics')->link());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Analytics');
        self::assertSelectorTextContains('body', 'Total submissions: 2');
        self::assertSelectorTextContains('body', 'In progress: 1');
        self::assertSelectorTextContains('body', 'PDF ready: 1');
        self::assertSelectorTextContains('body', 'PDFs emailed: 1');
        self::assertSelectorTextContains('body', 'Stored answers: 1');
        self::assertSelectorTextContains('body', '1040-NR');
    }

    public function testAClientCannotOpenAnalytics(): void
    {
        $clientUser = User::registerClient(
            'u-client',
            new Email('client@example.test'),
            $this->hash('password1'),
        );
        $this->users()->save($clientUser);
        $this->client->loginUser(SecurityUser::fromUser($clientUser));

        $this->client->request('GET', '/admin/analytics');
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    private function loginAdmin(): void
    {
        $admin = User::provisionAdmin(
            'u-admin',
            new Email('admin@example.test'),
            $this->hash('password1'),
        );
        $this->users()->save($admin);
        $this->client->loginUser(SecurityUser::fromUser($admin));
    }

    private function users(): UserRepositoryInterface
    {
        $users = static::getContainer()->get(UserRepositoryInterface::class);
        self::assertInstanceOf(UserRepositoryInterface::class, $users);

        return $users;
    }

    private function questionnaires(): QuestionnaireRepositoryInterface
    {
        $questionnaires = static::getContainer()->get(QuestionnaireRepositoryInterface::class);
        self::assertInstanceOf(QuestionnaireRepositoryInterface::class, $questionnaires);

        return $questionnaires;
    }

    private function submissions(): SubmissionRepositoryInterface
    {
        $submissions = static::getContainer()->get(SubmissionRepositoryInterface::class);
        self::assertInstanceOf(SubmissionRepositoryInterface::class, $submissions);

        return $submissions;
    }

    private function hash(string $plainPassword): string
    {
        $hasher = static::getContainer()->get(PasswordHasherInterface::class);
        self::assertInstanceOf(PasswordHasherInterface::class, $hasher);

        return $hasher->hash($plainPassword);
    }
}
