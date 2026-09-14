<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Security;

use App\Application\User\PasswordHasherInterface;
use App\Domain\Questionnaire\Entity\Questionnaire;
use App\Domain\Questionnaire\Repository\QuestionnaireRepositoryInterface;
use App\Domain\Questionnaire\ValueObject\QuestionType;
use App\Domain\Submission\Entity\QuestionnaireSubmission;
use App\Domain\Submission\Repository\SubmissionRepositoryInterface;
use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\ValueObject\Email;
use App\Infrastructure\Security\SecurityUser;
use App\Tests\Support\WebDatabaseTestCase;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticationAndAuthorizationTest extends WebDatabaseTestCase
{
    public function testAClientCanRegisterLoginAndReachTheClientArea(): void
    {
        $crawler = $this->client->request('GET', '/register');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Create account')->form([
            'registration_form[email]' => 'client@example.test',
            'registration_form[plainPassword][first]' => 'password1',
            'registration_form[plainPassword][second]' => 'password1',
        ]);
        $this->client->submit($form);
        self::assertResponseRedirects('/login');

        $crawler = $this->client->followRedirect();
        $login = $crawler->selectButton('Sign in')->form([
            '_username' => 'client@example.test',
            '_password' => 'password1',
        ]);
        $this->client->submit($login);
        $this->client->followRedirect();

        self::assertResponseRedirects('/client');
        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Client');
    }

    public function testRegistrationRejectsADuplicateEmailAndDoesNotCreateAdmins(): void
    {
        $this->persistUser(User::registerClient(
            'u-existing',
            new Email('taken@example.test'),
            $this->hash('password1'),
        ));

        $crawler = $this->client->request('GET', '/register');
        $form = $crawler->selectButton('Create account')->form([
            'registration_form[email]' => 'taken@example.test',
            'registration_form[plainPassword][first]' => 'password1',
            'registration_form[plainPassword][second]' => 'password1',
        ]);
        $this->client->submit($form);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSelectorTextContains('body', 'already registered');

        $users = static::getContainer()->get(UserRepositoryInterface::class);
        self::assertInstanceOf(UserRepositoryInterface::class, $users);
        $existing = $users->findByEmail(new Email('taken@example.test'));
        self::assertNotNull($existing);
        self::assertTrue($existing->isClient());
    }

    public function testClientsCannotAccessAdminAndAnonymousUsersAreSentToLogin(): void
    {
        $clientUser = $this->persistUser(User::registerClient(
            'u-client',
            new Email('client@example.test'),
            $this->hash('password1'),
        ));

        $this->client->request('GET', '/admin');
        self::assertResponseRedirects('/login');

        $this->client->loginUser(SecurityUser::fromUser($clientUser));
        $this->client->request('GET', '/admin');
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testLoginRejectsAnUnknownPassword(): void
    {
        $this->persistUser(User::registerClient(
            'u-client',
            new Email('client@example.test'),
            $this->hash('password1'),
        ));

        $crawler = $this->client->request('GET', '/login');
        $this->client->submit($crawler->selectButton('Sign in')->form([
            '_username' => 'client@example.test',
            '_password' => 'wrong-password',
        ]));
        self::assertResponseRedirects('/login');
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'Invalid email or password.');
        $this->client->request('GET', '/client');
        self::assertResponseRedirects('/login');
    }

    public function testAClientCannotViewAnotherClientsSubmission(): void
    {
        $owner = $this->persistUser(User::registerClient(
            'u-owner',
            new Email('owner@example.test'),
            $this->hash('password1'),
        ));
        $other = $this->persistUser(User::registerClient(
            'u-other',
            new Email('other@example.test'),
            $this->hash('password1'),
        ));
        $admin = $this->persistUser(User::provisionAdmin(
            'u-admin',
            new Email('admin@example.test'),
            $this->hash('password1'),
        ));

        $own = $this->persistSubmission('sub-own', $owner);
        $theirs = $this->persistSubmission('sub-other', $other);

        $this->client->request('GET', '/submissions/'.$own->id());
        self::assertResponseRedirects('/login');

        $this->client->loginUser(SecurityUser::fromUser($owner));
        $this->client->request('GET', '/submissions/'.$own->id());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Submission');

        $this->client->request('GET', '/submissions/'.$theirs->id());
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $this->client->loginUser(SecurityUser::fromUser($admin));
        $this->client->request('GET', '/submissions/'.$theirs->id());
        self::assertResponseIsSuccessful();
    }

    private function persistUser(User $user): User
    {
        $users = static::getContainer()->get(UserRepositoryInterface::class);
        self::assertInstanceOf(UserRepositoryInterface::class, $users);
        $users->save($user);

        return $user;
    }

    private function persistSubmission(string $id, User $user): QuestionnaireSubmission
    {
        $questionnaire = Questionnaire::create('q-'.$id, '1040-NR');
        $questionnaire->addStep('step-'.$id, 'Personal');
        $questionnaire->addQuestion('step-'.$id, 'q-name-'.$id, 'first_name', 'First name', QuestionType::ShortText);

        $questionnaires = static::getContainer()->get(QuestionnaireRepositoryInterface::class);
        self::assertInstanceOf(QuestionnaireRepositoryInterface::class, $questionnaires);
        $questionnaires->save($questionnaire);

        $submission = QuestionnaireSubmission::start(
            $id,
            $questionnaire,
            $user,
            new DateTimeImmutable('2026-01-01T10:00:00+00:00'),
        );

        $submissions = static::getContainer()->get(SubmissionRepositoryInterface::class);
        self::assertInstanceOf(SubmissionRepositoryInterface::class, $submissions);
        $submissions->save($submission);

        return $submission;
    }

    private function hash(string $plainPassword): string
    {
        $hasher = static::getContainer()->get(PasswordHasherInterface::class);
        self::assertInstanceOf(PasswordHasherInterface::class, $hasher);

        return $hasher->hash($plainPassword);
    }
}
