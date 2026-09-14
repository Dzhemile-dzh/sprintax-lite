<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Admin;

use App\Application\User\PasswordHasherInterface;
use App\Domain\Questionnaire\Repository\QuestionnaireRepositoryInterface;
use App\Domain\Questionnaire\ValueObject\FormType;
use App\Domain\Questionnaire\ValueObject\MappingSourceType;
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
        self::assertSelectorExists('#questionnaire_formType option[value="1040-nr"]');
        self::assertSelectorNotExists('#questionnaire_formType option[value="w-8ben"]');
        $form = $crawler->selectButton('Save')->form([
            'questionnaire[name]' => '1040-NR',
            'questionnaire[formType]' => FormType::Form1040Nr->value,
            'questionnaire[description]' => 'Demo form',
        ]);
        $this->client->submit($form);
        self::assertResponseRedirects();

        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('h1', '1040-NR');
        self::assertSelectorTextContains('body', 'Form type: Form 1040-NR (1040-nr)');
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

        $crawler = $this->client->click($crawler->selectLink('Add PDF mapping')->link());
        self::assertSelectorExists('#mapping_questionKey option[value="married"]');
        self::assertSelectorExists('#mapping_computedField option[value="tax_owed"]');
        $form = $crawler->selectButton('Save')->form([
            'mapping[sourceType]' => MappingSourceType::Question->value,
            'mapping[questionKey]' => 'married',
            'mapping[page]' => '1',
            'mapping[xMm]' => '20.5',
            'mapping[yMm]' => '40.25',
            'mapping[fontSize]' => '11',
        ]);
        $this->client->submit($form);
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'question: married');
        self::assertSelectorTextContains('body', '20.5mm, 40.25mm');

        $crawler = $this->client->click($crawler->selectLink('Edit step')->link());
        $this->client->submit($crawler->selectButton('Save')->form([
            'step[title]' => 'About you',
        ]));
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'About you');

        $crawler = $this->client->click($crawler->selectLink('Edit question')->eq(1)->link());
        self::assertSelectorExists('#question_visibilityQuestionKey option[value="married"]');
        $this->client->submit($crawler->selectButton('Save')->form([
            'question[label]' => 'Income sources',
        ]));
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'Income sources');

        $this->client->click($crawler->selectLink('Preview')->link());
        self::assertSelectorTextContains('h1', 'Preview: 1040-NR');
        self::assertSelectorTextContains('body', 'Married?');

        $stored = $this->questionnaires()->all()[0] ?? null;
        self::assertNotNull($stored);
        self::assertSame(FormType::Form1040Nr, $stored->formType());
        self::assertCount(1, $stored->steps());
        self::assertCount(2, $stored->allQuestions());
        self::assertCount(1, $stored->findQuestionByKey('income_types')?->options() ?? []);
        self::assertCount(1, $stored->mappings());
    }

    public function testAnAdminCanRenameAQuestionnaire(): void
    {
        $this->loginAdmin();

        $crawler = $this->client->request('GET', '/admin/questionnaires/new');
        $this->client->submit($crawler->selectButton('Save')->form([
            'questionnaire[name]' => '1040-NR',
            'questionnaire[formType]' => FormType::Form1040Nr->value,
            'questionnaire[description]' => 'Original',
        ]));
        $crawler = $this->client->followRedirect();

        $crawler = $this->client->click($crawler->selectLink('Edit')->link());
        $this->client->submit($crawler->selectButton('Save')->form([
            'questionnaire[name]' => '1040-NR Demo',
            'questionnaire[description]' => 'Updated copy',
        ]));
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('h1', '1040-NR Demo');
        self::assertSelectorTextContains('body', 'Updated copy');

        $stored = $this->questionnaires()->all()[0] ?? null;
        self::assertNotNull($stored);
        self::assertSame('1040-NR Demo', $stored->name());
        self::assertSame(FormType::Form1040Nr, $stored->formType());
        self::assertSame('Updated copy', $stored->description());
    }

    public function testAnAdminCannotChangeFormTypeAfterAClientStarts(): void
    {
        $this->loginAdmin();

        $crawler = $this->client->request('GET', '/admin/questionnaires/new');
        $this->client->submit($crawler->selectButton('Save')->form([
            'questionnaire[name]' => '1040-NR',
            'questionnaire[formType]' => FormType::Form1040Nr->value,
        ]));
        $this->client->followRedirect();

        $stored = $this->questionnaires()->all()[0] ?? null;
        self::assertNotNull($stored);
        $stored->addStep('step-1', 'Personal');
        $stored->addQuestion('step-1', 'q-name', 'first_name', 'First name', QuestionType::ShortText);
        $this->questionnaires()->save($stored);

        $clientUser = User::registerClient(
            'u-client-start',
            new Email('starter@example.test'),
            $this->hash('password1'),
        );
        $this->users()->save($clientUser);
        $this->submissions()->save(QuestionnaireSubmission::start(
            'sub-lock',
            $stored,
            $clientUser,
            new DateTimeImmutable('2026-01-01T10:00:00+00:00'),
        ));

        $crawler = $this->client->request('GET', '/admin/questionnaires/'.$stored->id().'/edit');
        self::assertSelectorExists('#questionnaire_formType[disabled]');
        self::assertSelectorTextContains('body', 'Form type cannot change after a client has started this questionnaire.');

        $this->client->submit($crawler->selectButton('Save')->form([
            'questionnaire[name]' => '1040-NR Demo',
        ]));
        $this->client->followRedirect();

        $reloaded = $this->questionnaires()->get($stored->id());
        self::assertSame('1040-NR Demo', $reloaded->name());
        self::assertSame(FormType::Form1040Nr, $reloaded->formType());
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
