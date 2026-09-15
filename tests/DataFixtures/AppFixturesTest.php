<?php

declare(strict_types=1);

namespace App\Tests\DataFixtures;

use App\DataFixtures\AppFixtures;
use App\Domain\Questionnaire\Entity\QuestionMapping;
use App\Domain\Questionnaire\Repository\QuestionnaireRepositoryInterface;
use App\Domain\Questionnaire\ValueObject\FormType;
use App\Domain\Questionnaire\ValueObject\QuestionType;
use App\Domain\Questionnaire\ValueObject\VisibilityOperator;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\ValueObject\Email;
use App\Infrastructure\Calculation\Form1040NrCalculator;
use App\Tests\Support\WebDatabaseTestCase;

final class AppFixturesTest extends WebDatabaseTestCase
{
    public function testItSeedsDemoUsersAndA1040NrQuestionnaire(): void
    {
        $fixtures = static::getContainer()->get(AppFixtures::class);
        self::assertInstanceOf(AppFixtures::class, $fixtures);
        $fixtures->load($this->entityManager);
        $this->entityManager->clear();

        $users = static::getContainer()->get(UserRepositoryInterface::class);
        self::assertInstanceOf(UserRepositoryInterface::class, $users);

        $admin = $users->findByEmail(new Email(AppFixtures::ADMIN_EMAIL));
        $client = $users->findByEmail(new Email(AppFixtures::CLIENT_EMAIL));
        self::assertNotNull($admin);
        self::assertNotNull($client);
        self::assertTrue($admin->isAdmin());
        self::assertTrue($client->isClient());

        $questionnaires = static::getContainer()->get(QuestionnaireRepositoryInterface::class);
        self::assertInstanceOf(QuestionnaireRepositoryInterface::class, $questionnaires);
        $all = $questionnaires->all();
        self::assertCount(1, $all);

        $questionnaire = $all[0];
        self::assertSame('1040-NR', $questionnaire->name());
        self::assertSame(FormType::Form1040Nr, $questionnaire->formType());
        self::assertCount(2, $questionnaire->steps());
        $wages = $questionnaire->findQuestionByKey('income_wages');
        self::assertNotNull($wages);
        self::assertTrue($wages->validation()->required);
        $wagesRule = $wages->visibility()->conditions[0] ?? null;
        self::assertNotNull($wagesRule);
        self::assertSame('income_types', $wagesRule->questionKey);
        self::assertSame('wages', $wagesRule->expectedValue);
        self::assertNotNull($questionnaire->findQuestionByKey('tax_withheld'));

        $spouse = $questionnaire->findQuestionByKey('spouse_name');
        self::assertNotNull($spouse);
        self::assertTrue($spouse->validation()->required);
        $spouseRule = $spouse->visibility()->conditions[0] ?? null;
        self::assertNotNull($spouseRule);
        self::assertSame('married', $spouseRule->questionKey);
        self::assertSame(VisibilityOperator::Equals, $spouseRule->operator);
        self::assertSame('yes', $spouseRule->expectedValue);

        $treaty = $questionnaire->findQuestionByKey('treaty_exempt_amount');
        self::assertNotNull($treaty);
        $treatyRule = $treaty->visibility()->conditions[0] ?? null;
        self::assertNotNull($treatyRule);
        self::assertSame('income_types', $treatyRule->questionKey);
        self::assertSame('treaty', $treatyRule->expectedValue);

        $residency = $questionnaire->findQuestionByKey('residency');
        self::assertNotNull($residency);
        self::assertSame(QuestionType::SingleChoice, $residency->type());
        self::assertCount(2, $residency->options());

        $firstName = $questionnaire->findQuestionByKey('first_name');
        self::assertNotNull($firstName);
        $mappingSources = array_map(
            static fn (QuestionMapping $mapping): string => $mapping->source()->reference,
            $questionnaire->mappings(),
        );
        self::assertContains($firstName->key(), $mappingSources);
        self::assertContains('last_name', $mappingSources);
        self::assertContains('birth_date', $mappingSources);
        self::assertContains('income_wages', $mappingSources);
        self::assertContains('treaty_exempt_amount', $mappingSources);
        self::assertContains(Form1040NrCalculator::FIELD_FILING_SINGLE, $mappingSources);
        self::assertContains(Form1040NrCalculator::FIELD_FILING_MFS, $mappingSources);
        self::assertContains(Form1040NrCalculator::FIELD_TOTAL_INCOME, $mappingSources);
        self::assertContains(Form1040NrCalculator::FIELD_TOTAL_ECI, $mappingSources);
        self::assertContains(Form1040NrCalculator::FIELD_ADJUSTED_GROSS_INCOME, $mappingSources);
        self::assertContains(Form1040NrCalculator::FIELD_TAXABLE_INCOME, $mappingSources);
        self::assertContains(Form1040NrCalculator::FIELD_TAX_OWED, $mappingSources);
        self::assertContains(Form1040NrCalculator::FIELD_TOTAL_TAX, $mappingSources);
        self::assertContains(Form1040NrCalculator::FIELD_TAX_WITHHELD, $mappingSources);
        self::assertContains(Form1040NrCalculator::FIELD_TOTAL_PAYMENTS, $mappingSources);
        self::assertContains(Form1040NrCalculator::FIELD_AMOUNT_OVERPAID, $mappingSources);
        self::assertContains(Form1040NrCalculator::FIELD_AMOUNT_OWED, $mappingSources);
        self::assertNotContains('married', $mappingSources);
        self::assertNotContains('residency', $mappingSources);
        self::assertNotContains('income_types', $mappingSources);
        self::assertCount(21, $questionnaire->mappings());

        $this->signIn(AppFixtures::ADMIN_EMAIL, AppFixtures::ADMIN_PASSWORD);
        $this->client->request('GET', '/admin');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Questionnaires');
        self::assertSelectorTextContains('body', '1040-NR');

        $this->client->submit($this->client->getCrawler()->selectButton('Log out')->form());

        $this->signIn(AppFixtures::CLIENT_EMAIL, AppFixtures::CLIENT_PASSWORD);
        $this->client->request('GET', '/client');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Client');
        self::assertSelectorTextContains('body', '1040-NR');
        self::assertSelectorTextContains('body', 'Start');

        $this->client->submit($this->client->getCrawler()->selectButton('Start')->form());
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', '1040-NR');
        self::assertSelectorTextContains('body', 'First name');
        self::assertSelectorTextNotContains('body', 'Spouse name');
    }

    private function signIn(string $email, string $password): void
    {
        $crawler = $this->client->request('GET', '/login');
        $this->client->submit($crawler->selectButton('Sign in')->form([
            '_username' => $email,
            '_password' => $password,
        ]));
        self::assertResponseRedirects();
        $this->client->followRedirect();
    }
}
