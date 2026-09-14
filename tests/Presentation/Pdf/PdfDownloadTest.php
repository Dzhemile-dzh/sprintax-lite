<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Pdf;

use App\Application\User\PasswordHasherInterface;
use App\Domain\Questionnaire\Entity\Questionnaire;
use App\Domain\Questionnaire\Repository\QuestionnaireRepositoryInterface;
use App\Domain\Questionnaire\ValueObject\FormType;
use App\Domain\Questionnaire\ValueObject\QuestionType;
use App\Domain\Submission\Entity\QuestionnaireSubmission;
use App\Domain\Submission\Repository\SubmissionRepositoryInterface;
use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\ValueObject\Email;
use App\Infrastructure\Security\SecurityUser;
use App\Tests\Support\WebDatabaseTestCase;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

final class PdfDownloadTest extends WebDatabaseTestCase
{
    /**
     * @var list<string>
     */
    private array $pdfFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->pdfFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    public function testTheOwnerCanDownloadAReadyPdfAndAnotherClientCannot(): void
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
        $submission = $this->persistReadySubmission('dl-owner', $owner, '%PDF-1.4 owner-file');

        $this->client->request('GET', '/submissions/'.$submission->id().'/pdf');
        self::assertResponseRedirects('/login');

        $this->client->loginUser(SecurityUser::fromUser($owner));
        $this->client->request('GET', '/submissions/'.$submission->id());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Download PDF');

        $this->client->request('GET', '/submissions/'.$submission->id().'/pdf');
        self::assertResponseIsSuccessful();
        $response = $this->client->getResponse();
        self::assertInstanceOf(BinaryFileResponse::class, $response);
        self::assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        self::assertStringContainsString('private', (string) $response->headers->get('cache-control'));
        self::assertStringContainsString('no-store', (string) $response->headers->get('cache-control'));
        self::assertSame('%PDF-1.4 owner-file', $response->getFile()->getContent());

        $this->client->loginUser(SecurityUser::fromUser($other));
        $this->client->request('GET', '/submissions/'.$submission->id().'/pdf');
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testAnAdminCanDownloadAnotherUsersPdf(): void
    {
        $owner = $this->persistUser(User::registerClient(
            'u-owner',
            new Email('owner@example.test'),
            $this->hash('password1'),
        ));
        $admin = $this->persistUser(User::provisionAdmin(
            'u-admin',
            new Email('admin@example.test'),
            $this->hash('password1'),
        ));
        $submission = $this->persistReadySubmission('dl-admin', $owner, '%PDF-1.4 admin-file');

        $this->client->loginUser(SecurityUser::fromUser($admin));
        $this->client->request('GET', '/submissions/'.$submission->id().'/pdf');
        self::assertResponseIsSuccessful();
        $response = $this->client->getResponse();
        self::assertInstanceOf(BinaryFileResponse::class, $response);
        self::assertSame('%PDF-1.4 admin-file', $response->getFile()->getContent());
    }

    public function testAFinalizedSubmissionCannotBeDownloadedUntilThePdfIsReady(): void
    {
        $owner = $this->persistUser(User::registerClient(
            'u-owner',
            new Email('owner@example.test'),
            $this->hash('password1'),
        ));
        $submission = $this->persistSubmission('dl-finalized', $owner);
        $submission->finalize(new DateTimeImmutable('2026-01-01T11:00:00+00:00'));
        $this->submissions()->save($submission);

        $this->client->loginUser(SecurityUser::fromUser($owner));
        $this->client->request('GET', '/submissions/'.$submission->id().'/pdf');
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $this->client->request('GET', '/client/submissions/'.$submission->id().'/done');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'prepared in the background');
        self::assertSelectorTextNotContains('body', 'Download PDF');
    }

    public function testAReadySubmissionWithAMissingFileReturnsNotFound(): void
    {
        $owner = $this->persistUser(User::registerClient(
            'u-owner',
            new Email('owner@example.test'),
            $this->hash('password1'),
        ));
        $submission = $this->persistSubmission('dl-missing', $owner);
        $submission->finalize(new DateTimeImmutable('2026-01-01T11:00:00+00:00'));
        $submission->markPdfReady(new DateTimeImmutable('2026-01-01T11:05:00+00:00'), 'dl-missing.pdf');
        $this->submissions()->save($submission);

        $this->client->loginUser(SecurityUser::fromUser($owner));
        $this->client->request('GET', '/submissions/'.$submission->id().'/pdf');
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testTheClientHomeOffersADownloadWhenThePdfIsReady(): void
    {
        $owner = $this->persistUser(User::registerClient(
            'u-owner',
            new Email('owner@example.test'),
            $this->hash('password1'),
        ));
        $this->persistReadySubmission('dl-home', $owner);

        $this->client->loginUser(SecurityUser::fromUser($owner));
        $this->client->request('GET', '/client');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Download PDF');
    }

    private function persistReadySubmission(
        string $id,
        User $user,
        string $contents = '%PDF-1.4',
    ): QuestionnaireSubmission {
        $submission = $this->persistSubmission($id, $user);
        $submission->finalize(new DateTimeImmutable('2026-01-01T11:00:00+00:00'));
        $submission->markPdfReady(new DateTimeImmutable('2026-01-01T11:05:00+00:00'), $id.'.pdf');
        $this->submissions()->save($submission);

        $directory = static::getContainer()->getParameter('kernel.project_dir').DIRECTORY_SEPARATOR.'var'.DIRECTORY_SEPARATOR.'pdf';
        self::assertIsString($directory);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $path = $directory.DIRECTORY_SEPARATOR.$id.'.pdf';
        file_put_contents($path, $contents);
        $this->pdfFiles[] = $path;

        return $submission;
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
        $questionnaire = Questionnaire::create('q-'.$id, '1040-NR', FormType::Form1040Nr);
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
        $this->submissions()->save($submission);

        return $submission;
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
