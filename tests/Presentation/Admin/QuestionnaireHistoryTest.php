<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Admin;

use App\Application\User\PasswordHasherInterface;
use App\Domain\Audit\Repository\QuestionnaireRevisionRepositoryInterface;
use App\Domain\Audit\ValueObject\RevisionAction;
use App\Domain\Questionnaire\Repository\QuestionnaireRepositoryInterface;
use App\Domain\Questionnaire\ValueObject\FormType;
use App\Domain\Questionnaire\ValueObject\QuestionType;
use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\ValueObject\Email;
use App\Infrastructure\Security\SecurityUser;
use App\Tests\Support\WebDatabaseTestCase;
use Symfony\Component\HttpFoundation\Response;

final class QuestionnaireHistoryTest extends WebDatabaseTestCase
{
    public function testEveryStructureChangeIsRecordedAgainstTheEditingAdmin(): void
    {
        $this->loginAdmin();

        $crawler = $this->client->request('GET', '/admin/questionnaires/new');
        $this->client->submit($crawler->selectButton('Save')->form([
            'questionnaire[name]' => '1040-NR',
            'questionnaire[formType]' => FormType::Form1040Nr->value,
        ]));
        $crawler = $this->client->followRedirect();

        $crawler = $this->client->click($crawler->selectLink('Add step')->link());
        $this->client->submit($crawler->selectButton('Save')->form([
            'step[title]' => 'Personal',
        ]));
        $crawler = $this->client->followRedirect();

        $crawler = $this->client->click($crawler->selectLink('Add question')->link());
        $this->client->submit($crawler->selectButton('Save')->form([
            'question[key]' => 'married',
            'question[label]' => 'Married?',
            'question[type]' => QuestionType::YesNo->value,
        ]));
        $this->client->followRedirect();

        $questionnaire = $this->questionnaires()->all()[0] ?? null;
        self::assertNotNull($questionnaire);

        $trail = $this->revisions()->forQuestionnaire($questionnaire->id());
        self::assertSame([3, 2, 1], array_map(
            static fn ($revision): int => $revision->version(),
            $trail,
        ));
        self::assertSame(
            [RevisionAction::QuestionAdded, RevisionAction::StepAdded, RevisionAction::QuestionnaireCreated],
            array_map(static fn ($revision): RevisionAction => $revision->action(), $trail),
        );
        self::assertSame('admin@example.test', $trail[0]->actor()->email);
    }

    public function testTheHistoryPageLinksToTheStructureOfEachVersion(): void
    {
        $this->loginAdmin();

        $crawler = $this->client->request('GET', '/admin/questionnaires/new');
        $this->client->submit($crawler->selectButton('Save')->form([
            'questionnaire[name]' => '1040-NR',
            'questionnaire[formType]' => FormType::Form1040Nr->value,
        ]));
        $crawler = $this->client->followRedirect();

        $crawler = $this->client->click($crawler->selectLink('Add step')->link());
        $this->client->submit($crawler->selectButton('Save')->form([
            'step[title]' => 'Personal',
        ]));
        $crawler = $this->client->followRedirect();

        $crawler = $this->client->click($crawler->selectLink('History')->link());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Change history');
        self::assertSelectorTextContains('body', 'Step added');
        self::assertSelectorTextContains('body', 'Added step "Personal" at position 1');
        self::assertSelectorTextContains('body', 'Questionnaire created');
        self::assertSelectorTextContains('body', 'admin@example.test');

        // The newest entry is listed first, so the second link points at version 1.
        $this->client->click($crawler->selectLink('View structure')->eq(1)->link());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Version 1');
        self::assertSelectorTextContains('body', 'No steps in this version.');
        self::assertSelectorTextContains('body', 'No mappings in this version.');
    }

    public function testAnUnknownVersionIsNotFoundAndClientsCannotReadTheTrail(): void
    {
        $this->loginAdmin();

        $crawler = $this->client->request('GET', '/admin/questionnaires/new');
        $this->client->submit($crawler->selectButton('Save')->form([
            'questionnaire[name]' => '1040-NR',
            'questionnaire[formType]' => FormType::Form1040Nr->value,
        ]));
        $this->client->followRedirect();

        $questionnaire = $this->questionnaires()->all()[0] ?? null;
        self::assertNotNull($questionnaire);

        $this->client->request('GET', '/admin/questionnaires/'.$questionnaire->id().'/history/99');
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $clientUser = User::registerClient(
            'u-client',
            new Email('client@example.test'),
            $this->hash('password1'),
        );
        $this->users()->save($clientUser);
        $this->client->loginUser(SecurityUser::fromUser($clientUser));

        $this->client->request('GET', '/admin/questionnaires/'.$questionnaire->id().'/history');
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

    private function revisions(): QuestionnaireRevisionRepositoryInterface
    {
        $revisions = static::getContainer()->get(QuestionnaireRevisionRepositoryInterface::class);
        self::assertInstanceOf(QuestionnaireRevisionRepositoryInterface::class, $revisions);

        return $revisions;
    }

    private function questionnaires(): QuestionnaireRepositoryInterface
    {
        $questionnaires = static::getContainer()->get(QuestionnaireRepositoryInterface::class);
        self::assertInstanceOf(QuestionnaireRepositoryInterface::class, $questionnaires);

        return $questionnaires;
    }

    private function users(): UserRepositoryInterface
    {
        $users = static::getContainer()->get(UserRepositoryInterface::class);
        self::assertInstanceOf(UserRepositoryInterface::class, $users);

        return $users;
    }

    private function hash(string $plainPassword): string
    {
        $hasher = static::getContainer()->get(PasswordHasherInterface::class);
        self::assertInstanceOf(PasswordHasherInterface::class, $hasher);

        return $hasher->hash($plainPassword);
    }
}
