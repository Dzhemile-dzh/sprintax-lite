<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Admin;

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

final class AdminSubmissionsListTest extends WebDatabaseTestCase
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

    public function testAnAdminSeesSubmissionsAndCanDownloadAReadyPdf(): void
    {
        $this->loginAdmin();

        $questionnaire = Questionnaire::create('q-1', '1040-NR', FormType::Form1040Nr);
        $questionnaire->addStep('step-1', 'Personal');
        $questionnaire->addQuestion(
            'step-1',
            'q-name',
            'first_name',
            'First name',
            QuestionType::ShortText,
        );
        $this->questionnaires()->save($questionnaire);

        $alice = User::registerClient('u-alice', new Email('alice@example.test'), $this->hash('password1'));
        $bob = User::registerClient('u-bob', new Email('bob@example.test'), $this->hash('password1'));
        $this->users()->save($alice);
        $this->users()->save($bob);

        $inProgress = QuestionnaireSubmission::start(
            'sub-1',
            $questionnaire,
            $alice,
            new DateTimeImmutable('2026-01-01T10:00:00+00:00'),
        );
        $this->submissions()->save($inProgress);

        $ready = QuestionnaireSubmission::start(
            'sub-2',
            $questionnaire,
            $bob,
            new DateTimeImmutable('2026-01-02T10:00:00+00:00'),
        );
        $ready->finalize(new DateTimeImmutable('2026-01-02T11:00:00+00:00'));
        $ready->markPdfReady(new DateTimeImmutable('2026-01-02T11:05:00+00:00'), 'sub-2.pdf');
        $this->submissions()->save($ready);
        $this->writePdf('sub-2.pdf', '%PDF-1.4 admin-list');

        $crawler = $this->client->request('GET', '/admin');
        self::assertResponseIsSuccessful();
        $crawler = $this->client->click($crawler->selectLink('Submissions')->link());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Submissions');
        self::assertSelectorTextContains('body', 'alice@example.test');
        self::assertSelectorTextContains('body', 'bob@example.test');
        self::assertSelectorTextContains('body', 'in_progress');
        self::assertSelectorTextContains('body', 'pdf_ready');
        self::assertSelectorTextContains('body', 'Download');

        $this->client->click($crawler->selectLink('Download')->link());
        self::assertResponseIsSuccessful();
        $response = $this->client->getResponse();
        self::assertInstanceOf(BinaryFileResponse::class, $response);
        self::assertSame('%PDF-1.4 admin-list', $response->getFile()->getContent());
    }

    public function testAClientCannotOpenAdminSubmissions(): void
    {
        $clientUser = User::registerClient(
            'u-client',
            new Email('client@example.test'),
            $this->hash('password1'),
        );
        $this->users()->save($clientUser);
        $this->client->loginUser(SecurityUser::fromUser($clientUser));

        $this->client->request('GET', '/admin/submissions');
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    private function writePdf(string $relativePath, string $contents): void
    {
        $directory = static::getContainer()->getParameter('kernel.project_dir').DIRECTORY_SEPARATOR.'var'.DIRECTORY_SEPARATOR.'pdf';
        self::assertIsString($directory);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $absolute = $directory.DIRECTORY_SEPARATOR.$relativePath;
        file_put_contents($absolute, $contents);
        $this->pdfFiles[] = $absolute;
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

    private function hash(string $plain): string
    {
        $hasher = static::getContainer()->get(PasswordHasherInterface::class);
        self::assertInstanceOf(PasswordHasherInterface::class, $hasher);

        return $hasher->hash($plain);
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
}
