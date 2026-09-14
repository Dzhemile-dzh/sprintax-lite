<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use App\Application\Pdf\SubmissionPdfMailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

final class SymfonySubmissionPdfMailer implements SubmissionPdfMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly string $fromAddress,
    ) {
    }

    public function send(
        string $recipientEmail,
        string $questionnaireName,
        string $absolutePdfPath,
        string $downloadFileName,
    ): void {
        $body = $this->twig->render('emails/submission_pdf.txt.twig', [
            'questionnaire_name' => $questionnaireName,
        ]);

        $email = (new Email())
            ->from($this->fromAddress)
            ->to($recipientEmail)
            ->subject(sprintf('Your %s PDF is ready', $questionnaireName))
            ->text($body)
            ->attachFromPath($absolutePdfPath, $downloadFileName, 'application/pdf');

        $this->mailer->send($email);
    }
}
