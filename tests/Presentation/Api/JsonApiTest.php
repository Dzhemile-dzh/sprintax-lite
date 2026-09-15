<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Api;

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

final class JsonApiTest extends WebDatabaseTestCase
{
    public function testAnAdminCanListAndInspectQuestionnairesAsJson(): void
    {
        $this->loginAdmin();
        $questionnaire = $this->seedQuestionnaire();

        $this->client->request('GET', '/api/questionnaires');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/json');
        $list = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($list);
        self::assertSame('1040-NR', $list['questionnaires'][0]['name']);
        self::assertSame('1040-nr', $list['questionnaires'][0]['formType']);

        $this->client->request('GET', '/api/questionnaires/'.$questionnaire->id());
        self::assertResponseIsSuccessful();
        $detail = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($detail);
        self::assertSame('Personal', $detail['questionnaire']['steps'][0]['title']);
        self::assertSame('first_name', $detail['questionnaire']['steps'][0]['questions'][0]['key']);
    }

    public function testAClientCannotListQuestionnairesButCanReadTheirOwnSubmission(): void
    {
        $questionnaire = $this->seedQuestionnaire();
        $owner = User::registerClient('u-owner', new Email('owner@example.test'), $this->hash('password1'));
        $other = User::registerClient('u-other', new Email('other@example.test'), $this->hash('password1'));
        $this->users()->save($owner);
        $this->users()->save($other);

        $submission = QuestionnaireSubmission::start(
            'sub-api',
            $questionnaire,
            $owner,
            new DateTimeImmutable('2026-01-01T10:00:00+00:00'),
        );
        $question = $questionnaire->findQuestionByKey('first_name');
        self::assertNotNull($question);
        $submission->recordAnswer($question, AnswerValue::text('Pat'), new DateTimeImmutable('2026-01-01T10:05:00+00:00'));
        $this->submissions()->save($submission);

        $this->client->loginUser(SecurityUser::fromUser($owner));
        $this->client->request('GET', '/api/questionnaires');
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $this->client->request('GET', '/api/submissions/'.$submission->id());
        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($payload);
        self::assertSame('in_progress', $payload['submission']['status']);
        self::assertSame('first_name', $payload['submission']['answers'][0]['questionKey']);
        self::assertSame('Pat', $payload['submission']['answers'][0]['value']);

        $this->client->loginUser(SecurityUser::fromUser($other));
        $this->client->request('GET', '/api/submissions/'.$submission->id());
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    private function seedQuestionnaire(): Questionnaire
    {
        $questionnaire = Questionnaire::create('q-api', '1040-NR', FormType::Form1040Nr, 'Demo');
        $questionnaire->addStep('step-1', 'Personal');
        $questionnaire->addQuestion('step-1', 'q-name', 'first_name', 'First name', QuestionType::ShortText);
        $this->questionnaires()->save($questionnaire);

        return $questionnaire;
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
