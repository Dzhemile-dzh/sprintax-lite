<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Client;

use App\Application\User\PasswordHasherInterface;
use App\Domain\Questionnaire\Entity\Questionnaire;
use App\Domain\Questionnaire\Repository\QuestionnaireRepositoryInterface;
use App\Domain\Questionnaire\ValueObject\QuestionType;
use App\Domain\Questionnaire\ValueObject\QuestionValidation;
use App\Domain\Questionnaire\ValueObject\VisibilityCondition;
use App\Domain\Questionnaire\ValueObject\VisibilityOperator;
use App\Domain\Questionnaire\ValueObject\VisibilityRule;
use App\Domain\Submission\Repository\SubmissionRepositoryInterface;
use App\Domain\Submission\ValueObject\SubmissionStatus;
use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\ValueObject\Email;
use App\Infrastructure\Security\SecurityUser;
use App\Tests\Support\WebDatabaseTestCase;

final class WizardVisibilityTest extends WebDatabaseTestCase
{
    public function testAMaliciousPostCannotSaveAHiddenQuestion(): void
    {
        $clientUser = $this->loginClient();
        $this->persistConditionalQuestionnaire();

        $crawler = $this->client->request('GET', '/client');
        $this->client->submit($crawler->selectButton('Start')->form());
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextNotContains('body', 'Spouse name');

        $form = $crawler->selectButton('Continue')->form([
            'wizard_step[first_name]' => 'Ada',
            'wizard_step[married]' => 'no',
        ]);
        $values = $form->getPhpValues();
        self::assertArrayHasKey('wizard_step', $values);
        self::assertIsArray($values['wizard_step']);
        $values['wizard_step']['spouse_name'] = 'Hacker';
        $this->client->request($form->getMethod(), $form->getUri(), $values);
        self::assertResponseRedirects();
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Review');
        self::assertSelectorTextContains('body', 'Ada');
        self::assertSelectorTextNotContains('body', 'Spouse name');
        self::assertSelectorTextNotContains('body', 'Hacker');

        $this->client->submit($crawler->selectButton('Submit')->form());
        $submission = $this->submissions()->findForUser($clientUser->id())[0];
        self::assertSame(SubmissionStatus::Finalized, $submission->status());
        self::assertArrayNotHasKey('spouse_name', $submission->answersByQuestionKey());
        self::assertSame('Ada', $submission->answersByQuestionKey()['first_name']->raw());
    }

    public function testChangingAConditionClearsThePreviouslyVisibleAnswer(): void
    {
        $clientUser = $this->loginClient();
        $this->persistConditionalQuestionnaire();

        $crawler = $this->client->request('GET', '/client');
        $this->client->submit($crawler->selectButton('Start')->form());
        $crawler = $this->client->followRedirect();

        $crawler = $this->client->submit($crawler->selectButton('Continue')->form([
            'wizard_step[first_name]' => 'Ada',
            'wizard_step[married]' => 'yes',
        ]));
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'Spouse name');

        $crawler = $this->client->submit($crawler->selectButton('Continue')->form([
            'wizard_step[first_name]' => 'Ada',
            'wizard_step[married]' => 'yes',
            'wizard_step[spouse_name]' => 'Charles',
        ]));
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'Charles');

        $crawler = $this->client->click($crawler->selectLink('Edit this step')->link());
        $crawler = $this->client->submit($crawler->selectButton('Continue')->form([
            'wizard_step[first_name]' => 'Ada',
            'wizard_step[married]' => 'no',
        ]));
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Review');
        self::assertSelectorTextNotContains('body', 'Spouse name');
        self::assertSelectorTextNotContains('body', 'Charles');

        $this->client->submit($crawler->selectButton('Submit')->form());
        $submission = $this->submissions()->findForUser($clientUser->id())[0];
        self::assertSame(SubmissionStatus::Finalized, $submission->status());
        self::assertArrayNotHasKey('spouse_name', $submission->answersByQuestionKey());
    }

    private function persistConditionalQuestionnaire(): Questionnaire
    {
        $questionnaire = Questionnaire::create('q-vis', 'Visibility');
        $questionnaire->addStep('step-1', 'Personal');
        $questionnaire->addQuestion(
            'step-1',
            'q-name',
            'first_name',
            'First name',
            QuestionType::ShortText,
            null,
            QuestionValidation::required(),
        );
        $questionnaire->addQuestion(
            'step-1',
            'q-married',
            'married',
            'Married?',
            QuestionType::YesNo,
            null,
            QuestionValidation::required(),
        );
        $questionnaire->addQuestion(
            'step-1',
            'q-spouse',
            'spouse_name',
            'Spouse name',
            QuestionType::ShortText,
            null,
            QuestionValidation::required(),
            new VisibilityRule([
                new VisibilityCondition('married', VisibilityOperator::Equals, 'yes'),
            ]),
        );
        $this->questionnaires()->save($questionnaire);

        return $questionnaire;
    }

    private function loginClient(): User
    {
        $clientUser = User::registerClient(
            'u-client',
            new Email('client@example.test'),
            $this->hash('password1'),
        );
        $this->users()->save($clientUser);
        $this->client->loginUser(SecurityUser::fromUser($clientUser));

        return $clientUser;
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
