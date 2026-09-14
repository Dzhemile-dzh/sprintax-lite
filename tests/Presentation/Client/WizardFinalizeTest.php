<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Client;

use App\Application\Submission\Finalize\FinalizeSubmission;
use App\Application\Submission\SaveStep\SaveStep;
use App\Application\Submission\Start\StartSubmission;
use App\Application\User\PasswordHasherInterface;
use App\Domain\Questionnaire\Entity\Questionnaire;
use App\Domain\Questionnaire\Repository\QuestionnaireRepositoryInterface;
use App\Domain\Questionnaire\ValueObject\FormType;
use App\Domain\Questionnaire\ValueObject\QuestionType;
use App\Domain\Questionnaire\ValueObject\QuestionValidation;
use App\Domain\Submission\Repository\SubmissionRepositoryInterface;
use App\Domain\Submission\ValueObject\SubmissionStatus;
use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\ValueObject\Email;
use App\Infrastructure\Messenger\Message\GenerateSubmissionPdfMessage;
use App\Infrastructure\Security\SecurityUser;
use App\Tests\Support\WebDatabaseTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class WizardFinalizeTest extends WebDatabaseTestCase
{
    public function testFinalizeDispatchesPdfGenerationWithoutCreatingThePdf(): void
    {
        $clientUser = $this->loginClient();
        $this->persistQuestionnaire();

        $crawler = $this->client->request('GET', '/client');
        $this->client->submit($crawler->selectButton('Start')->form());
        $crawler = $this->client->followRedirect();
        $crawler = $this->client->submit($crawler->selectButton('Continue')->form([
            'wizard_step[first_name]' => 'Ada',
        ]));
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Review');

        $this->client->submit($crawler->selectButton('Submit')->form());
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Submitted');
        self::assertSelectorTextContains('body', 'prepared in the background');

        $submission = $this->submissions()->findForUser($clientUser->id())[0];
        self::assertSame(SubmissionStatus::Finalized, $submission->status());
        self::assertFileDoesNotExist(
            static::getContainer()->getParameter('kernel.project_dir').'/var/pdf/'.$submission->id().'.pdf',
        );
    }

    public function testFinalizeQueuesThePdfMessageOnTheAsyncTransport(): void
    {
        $clientUser = $this->persistUser(User::registerClient(
            'u-client',
            new Email('client@example.test'),
            $this->hash('password1'),
        ));
        $questionnaire = $this->persistQuestionnaire();

        $start = static::getContainer()->get(StartSubmission::class);
        self::assertInstanceOf(StartSubmission::class, $start);
        $submission = $start->execute($clientUser->id(), $questionnaire->id());
        $stepId = $questionnaire->firstStep()?->id();
        self::assertNotNull($stepId);

        $save = static::getContainer()->get(SaveStep::class);
        self::assertInstanceOf(SaveStep::class, $save);
        $save->execute($submission->id(), $stepId, ['first_name' => 'Ada'], true);

        $finalize = static::getContainer()->get(FinalizeSubmission::class);
        self::assertInstanceOf(FinalizeSubmission::class, $finalize);
        $finalize->execute($submission->id(), $clientUser->id(), false);

        $transport = static::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);
        $messages = array_map(
            static fn (Envelope $envelope): object => $envelope->getMessage(),
            $transport->getSent(),
        );
        self::assertCount(1, $messages);
        self::assertInstanceOf(GenerateSubmissionPdfMessage::class, $messages[0]);
        self::assertSame($submission->id(), $messages[0]->submissionId);
        self::assertFileDoesNotExist(
            static::getContainer()->getParameter('kernel.project_dir').'/var/pdf/'.$submission->id().'.pdf',
        );
    }

    private function persistQuestionnaire(): Questionnaire
    {
        $questionnaire = Questionnaire::create('q-finalize', '1040-NR', FormType::Form1040Nr);
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
        $this->questionnaires()->save($questionnaire);

        return $questionnaire;
    }

    private function persistUser(User $user): User
    {
        $this->users()->save($user);

        return $user;
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
