<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Mail;

use App\Infrastructure\Mail\SymfonySubmissionPdfMailer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class SymfonySubmissionPdfMailerTest extends TestCase
{
    public function testItSendsThePdfAsAnAttachmentToTheClient(): void
    {
        $pdfPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sprintax-mail-'.uniqid('', true).'.pdf';
        file_put_contents($pdfPath, '%PDF-1.4 fixture');

        $captured = null;
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())
            ->method('send')
            ->with(self::callback(static function (Email $email) use (&$captured): bool {
                $captured = $email;

                return true;
            }));

        $templates = dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'templates';
        $sender = new SymfonySubmissionPdfMailer(
            $mailer,
            new Environment(new FilesystemLoader($templates)),
            'noreply@example.test',
        );

        $sender->send('client@example.test', '1040-NR', $pdfPath, 'sub-1.pdf');

        self::assertInstanceOf(Email::class, $captured);
        self::assertSame(['noreply@example.test'], $this->addresses($captured->getFrom()));
        self::assertSame(['client@example.test'], $this->addresses($captured->getTo()));
        self::assertSame('Your 1040-NR PDF is ready', $captured->getSubject());
        self::assertStringContainsString('1040-NR PDF is attached', (string) $captured->getTextBody());
        self::assertCount(1, $captured->getAttachments());
        self::assertSame('sub-1.pdf', $captured->getAttachments()[0]->getName());
        self::assertSame('application/pdf', $captured->getAttachments()[0]->getContentType());
    }

    /**
     * @param array<int, \Symfony\Component\Mime\Address> $addresses
     *
     * @return list<string>
     */
    private function addresses(array $addresses): array
    {
        $values = [];

        foreach ($addresses as $address) {
            $values[] = $address->getAddress();
        }

        return $values;
    }
}
