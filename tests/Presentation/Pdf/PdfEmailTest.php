<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Pdf;

use App\Application\Pdf\EmailSubmissionPdf;
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
use App\Tests\Support\WebDatabaseTestCase;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;

final class PdfEmailTest extends WebDatabaseTestCase
{
    use MailerAssertionsTrait;

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

    public function testAReadyPdfIsEmailedToTheClientOnce(): void
    {
        $owner = $this->persistUser(User::registerClient(
            'u-owner',
            new Email('owner@example.test'),
            $this->hash('password1'),
        ));
        $submission = $this->persistReadySubmission('mail-ready', $owner);

        $emailer = static::getContainer()->get(EmailSubmissionPdf::class);
        self::assertInstanceOf(EmailSubmissionPdf::class, $emailer);

        $emailer->execute($submission->id());
        $emailer->execute($submission->id());

        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertNotNull($email);
        self::assertEmailAddressContains($email, 'from', 'noreply@example.test');
        self::assertEmailAddressContains($email, 'to', 'owner@example.test');
        self::assertEmailSubjectContains($email, '1040-NR');
        self::assertEmailTextBodyContains($email, 'PDF is attached');
        self::assertEmailAttachmentCount($email, 1);

        $reloaded = $this->submissions()->get($submission->id());
        self::assertTrue($reloaded->pdfWasEmailed());
    }

    private function persistReadySubmission(string $id, User $user): QuestionnaireSubmission
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
        $submission->finalize(new DateTimeImmutable('2026-01-01T11:00:00+00:00'));
        $submission->markPdfReady(new DateTimeImmutable('2026-01-01T11:05:00+00:00'), $id.'.pdf');
        $this->submissions()->save($submission);

        $directory = static::getContainer()->getParameter('kernel.project_dir').DIRECTORY_SEPARATOR.'var'.DIRECTORY_SEPARATOR.'pdf';
        self::assertIsString($directory);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $path = $directory.DIRECTORY_SEPARATOR.$id.'.pdf';
        file_put_contents($path, '%PDF-1.4 emailed');
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
