<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Client;

use App\Application\User\PasswordHasherInterface;
use App\Domain\Questionnaire\Entity\QuestionOption;
use App\Domain\Questionnaire\Entity\Questionnaire;
use App\Domain\Questionnaire\Repository\QuestionnaireRepositoryInterface;
use App\Domain\Questionnaire\ValueObject\FormType;
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
use Symfony\Component\HttpFoundation\Response;

final class WizardTest extends WebDatabaseTestCase
{
    public function testAnAdminCannotOpenTheClientWizard(): void
    {
        $admin = User::provisionAdmin(
            'u-admin',
            new Email('admin@example.test'),
            $this->hash('password1'),
        );
        $this->users()->save($admin);
        $this->client->loginUser(SecurityUser::fromUser($admin));

        $this->client->request('GET', '/client');
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $this->client->request('POST', '/client/questionnaires/q-1/start');
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testAClientCanCompleteTheWizardAndResumeFromTheClientHome(): void
    {
        $clientUser = $this->loginClient();
        $questionnaire = $this->persistWizardQuestionnaire();
        $steps = $questionnaire->steps();
        $firstStepId = $steps[0]->id();
        $secondStepId = $steps[1]->id();

        $crawler = $this->client->request('GET', '/client');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Client');
        self::assertSelectorTextContains('body', '1040-NR');

        $this->client->submit($crawler->selectButton('Start')->form());
        $submissionId = $this->submissionId($clientUser->id());
        self::assertResponseRedirects('/client/submissions/'.$submissionId.'/steps/'.$firstStepId);

        $this->client->request(
            'GET',
            '/client/submissions/'.$submissionId.'/steps/'.$secondStepId,
        );
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $crawler = $this->client->request('GET', '/client/submissions/'.$submissionId.'/steps/'.$firstStepId);
        self::assertSelectorTextContains('h2', '1. Personal');

        $this->client->request('GET', '/client/submissions/'.$submissionId.'/review');
        self::assertResponseRedirects('/client/submissions/'.$submissionId.'/steps/'.$firstStepId);

        $crawler = $this->client->submit($crawler->selectButton('Continue')->form([
            'wizard_step[first_name]' => 'Ada',
            'wizard_step[birth_date]' => '1990-05-01',
            'wizard_step[married]' => 'no',
        ]));
        self::assertResponseRedirects('/client/submissions/'.$submissionId.'/steps/'.$secondStepId);

        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('h2', '2. Income');
        self::assertSelectorTextNotContains('body', 'Spouse name');

        $home = $this->client->request('GET', '/client');
        self::assertSelectorTextContains('body', 'Continue');
        $crawler = $this->client->click($home->selectLink('Continue')->link());
        self::assertSelectorTextContains('h2', '2. Income');

        $this->client->request('GET', '/client/submissions/'.$submissionId.'/review');
        self::assertResponseRedirects('/client/submissions/'.$submissionId.'/steps/'.$secondStepId);

        $crawler = $this->client->submit($crawler->selectButton('Continue')->form([
            'wizard_step[residency]' => 'nonresident',
            'wizard_step[income_types]' => ['wages'],
            'wizard_step[wages]' => '50000',
        ]));
        self::assertResponseRedirects('/client/submissions/'.$submissionId.'/review');

        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Review');
        self::assertSelectorTextContains('body', 'Ada');
        self::assertSelectorTextContains('body', '1990-05-01');
        self::assertSelectorTextContains('body', 'no');
        self::assertSelectorTextContains('body', 'nonresident');
        self::assertSelectorTextContains('body', 'wages');
        self::assertSelectorTextContains('body', '50000');
        self::assertSelectorTextNotContains('body', 'Spouse name');

        $crawler = $this->client->click($crawler->selectLink('Edit this step')->link());
        self::assertSelectorTextContains('h2', '1. Personal');
        self::assertSelectorExists('input[name="wizard_step[first_name]"][value="Ada"]');

        $crawler = $this->client->submit($crawler->selectButton('Continue')->form([
            'wizard_step[first_name]' => 'Ada',
            'wizard_step[birth_date]' => '1990-05-01',
            'wizard_step[married]' => 'yes',
        ]));
        self::assertResponseRedirects('/client/submissions/'.$submissionId.'/steps/'.$secondStepId);

        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'Spouse name');

        $crawler = $this->client->submit($crawler->selectButton('Continue')->form([
            'wizard_step[residency]' => 'nonresident',
            'wizard_step[income_types]' => ['wages'],
            'wizard_step[wages]' => '50000',
            'wizard_step[spouse_name]' => 'Charles',
        ]));
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Review');
        self::assertSelectorTextContains('body', 'Charles');
        self::assertSelectorTextContains('body', 'yes');

        $this->client->submit($crawler->selectButton('Submit')->form());
        self::assertResponseRedirects('/client/submissions/'.$submissionId.'/done');

        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Submitted');

        $this->client->request('GET', '/client/submissions/'.$submissionId.'/steps/'.$firstStepId);
        self::assertResponseRedirects('/client/submissions/'.$submissionId.'/review');

        $stored = $this->submissions()->get($submissionId);
        self::assertSame(SubmissionStatus::Finalized, $stored->status());
        self::assertSame('Charles', $stored->answersByQuestionKey()['spouse_name']->raw());
    }

    public function testAMissingRequiredAnswerKeepsTheClientOnTheStep(): void
    {
        $clientUser = $this->loginClient();
        $questionnaire = $this->persistWizardQuestionnaire();
        $firstStepId = $questionnaire->steps()[0]->id();

        $crawler = $this->client->request('GET', '/client');
        $this->client->submit($crawler->selectButton('Start')->form());
        $submissionId = $this->submissionId($clientUser->id());
        $crawler = $this->client->followRedirect();

        $this->client->submit($crawler->selectButton('Continue')->form([
            'wizard_step[first_name]' => '',
            'wizard_step[birth_date]' => '1990-05-01',
            'wizard_step[married]' => 'no',
        ]));
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSelectorTextContains('h2', '1. Personal');
        self::assertSame($firstStepId, $this->submissions()->get($submissionId)->currentStepId());
        self::assertArrayNotHasKey('first_name', $this->submissions()->get($submissionId)->answersByQuestionKey());
    }

    public function testAClientCannotOpenAnotherClientsWizard(): void
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
        $this->persistWizardQuestionnaire();

        $this->client->loginUser(SecurityUser::fromUser($owner));
        $this->client->request('GET', '/client');
        $this->client->submit($this->client->getCrawler()->selectButton('Start')->form());
        $submissionId = $this->submissionId($owner->id());
        $stepId = $this->questionnaires()->all()[0]->firstStep()?->id();
        self::assertNotNull($stepId);

        $this->client->loginUser(SecurityUser::fromUser($other));
        $this->client->request('GET', '/client/submissions/'.$submissionId.'/steps/'.$stepId);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->client->request('GET', '/client/submissions/'.$submissionId.'/review');
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    private function loginClient(): User
    {
        $clientUser = $this->persistUser(User::registerClient(
            'u-client',
            new Email('client@example.test'),
            $this->hash('password1'),
        ));
        $this->client->loginUser(SecurityUser::fromUser($clientUser));

        return $clientUser;
    }

    private function persistWizardQuestionnaire(): Questionnaire
    {
        $questionnaire = Questionnaire::create('q-1', '1040-NR', FormType::Form1040Nr);
        $questionnaire->addStep('step-1', 'Personal');
        $questionnaire->addStep('step-2', 'Income');
        $questionnaire->addQuestion(
            'step-1',
            'q-name',
            'first_name',
            'First name',
            QuestionType::ShortText,
            null,
            QuestionValidation::required(),
        );
        $questionnaire->addQuestion('step-1', 'q-birth', 'birth_date', 'Birth date', QuestionType::Date);
        $questionnaire->addQuestion(
            'step-1',
            'q-married',
            'married',
            'Married?',
            QuestionType::YesNo,
            null,
            QuestionValidation::required(),
        );
        $residency = $questionnaire->addQuestion(
            'step-2',
            'q-residency',
            'residency',
            'Residency',
            QuestionType::SingleChoice,
            null,
            QuestionValidation::required(),
        );
        $residency->addOption(QuestionOption::create('opt-resident', 'Resident', 'resident', 1));
        $residency->addOption(QuestionOption::create('opt-nr', 'Nonresident', 'nonresident', 2));
        $incomeTypes = $questionnaire->addQuestion(
            'step-2',
            'q-income-types',
            'income_types',
            'Income types',
            QuestionType::MultiChoice,
        );
        $incomeTypes->addOption(QuestionOption::create('opt-wages', 'Wages', 'wages', 1));
        $incomeTypes->addOption(QuestionOption::create('opt-interest', 'Interest', 'interest', 2));
        $questionnaire->addQuestion(
            'step-2',
            'q-wages',
            'wages',
            'Wages',
            QuestionType::Number,
            null,
            QuestionValidation::required(),
        );
        $questionnaire->addQuestion(
            'step-2',
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

    private function persistUser(User $user): User
    {
        $this->users()->save($user);

        return $user;
    }

    private function submissionId(string $userId): string
    {
        $submissions = $this->submissions()->findForUser($userId);
        self::assertNotSame([], $submissions);

        return $submissions[0]->id();
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
