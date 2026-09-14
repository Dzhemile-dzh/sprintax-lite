<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Admin;

use App\Application\User\PasswordHasherInterface;
use App\Domain\Questionnaire\Repository\QuestionnaireRepositoryInterface;
use App\Domain\Questionnaire\ValueObject\MappingSourceType;
use App\Domain\Questionnaire\ValueObject\QuestionType;
use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\ValueObject\Email;
use App\Infrastructure\Security\SecurityUser;
use App\Tests\Support\WebDatabaseTestCase;
use Symfony\Component\HttpFoundation\Response;

final class QuestionnaireBuilderTest extends WebDatabaseTestCase
{
    public function testAClientCannotOpenTheAdminBuilder(): void
    {
        $clientUser = User::registerClient(
            'u-client',
            new Email('client@example.test'),
            $this->hash('password1'),
        );
        $this->users()->save($clientUser);
        $this->client->loginUser(SecurityUser::fromUser($clientUser));

        $this->client->request('GET', '/admin');
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $this->client->request('GET', '/admin/questionnaires/new');
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testAnAdminCanBuildAQuestionnaireWithStepsQuestionsOptionsAndMappings(): void
    {
        $this->loginAdmin();

        $crawler = $this->client->request('GET', '/admin');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Questionnaires');

        $crawler = $this->client->click($crawler->selectLink('New questionnaire')->link());
        $form = $crawler->selectButton('Save')->form([
            'questionnaire[name]' => '1040-NR',
            'questionnaire[description]' => 'Demo form',
        ]);
        $this->client->submit($form);
        self::assertResponseRedirects();

        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('h1', '1040-NR');
        self::assertSelectorTextContains('body', 'Demo form');

        $crawler = $this->client->click($crawler->selectLink('Add step')->link());
        $form = $crawler->selectButton('Save')->form([
            'step[title]' => 'Personal',
        ]);
        $this->client->submit($form);
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'Personal');

        $crawler = $this->client->click($crawler->selectLink('Add question')->link());
        $form = $crawler->selectButton('Save')->form([
            'question[key]' => 'married',
            'question[label]' => 'Married?',
            'question[type]' => QuestionType::YesNo->value,
        ]);
        $this->client->submit($form);
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'Married?');

        $crawler = $this->client->click($crawler->selectLink('Add question')->link());
        $form = $crawler->selectButton('Save')->form([
            'question[key]' => 'income_types',
            'question[label]' => 'Income types',
            'question[type]' => QuestionType::SingleChoice->value,
            'question[required]' => '1',
        ]);
        $this->client->submit($form);
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'Income types');

        $crawler = $this->client->click($crawler->selectLink('Add option')->link());
        $form = $crawler->selectButton('Save')->form([
            'option[label]' => 'Wages',
            'option[value]' => 'wages',
        ]);
        $this->client->submit($form);
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'Wages = wages');

        preg_match('/id: ([a-f0-9]+)/', $crawler->text(), $matches);
        self::assertArrayHasKey(1, $matches);

        $crawler = $this->client->click($crawler->selectLink('Add PDF mapping')->link());
        $form = $crawler->selectButton('Save')->form([
            'mapping[sourceType]' => MappingSourceType::Question->value,
            'mapping[sourceReference]' => $matches[1],
            'mapping[page]' => '1',
            'mapping[xMm]' => '20.5',
            'mapping[yMm]' => '40.25',
            'mapping[fontSize]' => '11',
        ]);
        $this->client->submit($form);
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'question: '.$matches[1]);
        self::assertSelectorTextContains('body', '20.5mm, 40.25mm');

        $this->client->click($crawler->selectLink('Preview')->link());
        self::assertSelectorTextContains('h1', 'Preview: 1040-NR');
        self::assertSelectorTextContains('body', 'Married?');

        $stored = $this->questionnaires()->all()[0] ?? null;
        self::assertNotNull($stored);
        self::assertCount(1, $stored->steps());
        self::assertCount(2, $stored->allQuestions());
        self::assertCount(1, $stored->findQuestionByKey('income_types')?->options() ?? []);
        self::assertCount(1, $stored->mappings());
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

    private function hash(string $plainPassword): string
    {
        $hasher = static::getContainer()->get(PasswordHasherInterface::class);
        self::assertInstanceOf(PasswordHasherInterface::class, $hasher);

        return $hasher->hash($plainPassword);
    }
}
