<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Admin;

use App\Application\User\PasswordHasherInterface;
use App\Domain\Questionnaire\Entity\Questionnaire;
use App\Domain\Questionnaire\Repository\QuestionnaireRepositoryInterface;
use App\Domain\Questionnaire\ValueObject\FormType;
use App\Domain\Questionnaire\ValueObject\QuestionType;
use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\ValueObject\Email;
use App\Infrastructure\Security\SecurityUser;
use App\Tests\Support\WebDatabaseTestCase;
use Symfony\Component\HttpFoundation\Response;

final class CoordinatePickerTest extends WebDatabaseTestCase
{
    public function testTheMappingFormShowsAPickerWhenTheTemplateExists(): void
    {
        $this->loginAdmin();
        $questionnaire = $this->seedQuestionnaire();
        $templatesDir = static::getContainer()->getParameter('app.pdf.templates_dir');
        self::assertIsString($templatesDir);

        if (!is_dir($templatesDir)) {
            mkdir($templatesDir, 0775, true);
        }

        $templatePath = $templatesDir.DIRECTORY_SEPARATOR.'1040-nr.pdf';
        $created = !is_file($templatePath);
        if ($created) {
            file_put_contents($templatePath, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n");
        }

        try {
            $crawler = $this->client->request('GET', '/admin/questionnaires/'.$questionnaire->id().'/mappings/new');
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('.pdf-picker h2', 'Coordinate picker');
            self::assertSelectorExists('#pdf-picker-canvas');
            self::assertSelectorExists('body script[src*="pdf.js"]');
            $html = (string) $this->client->getResponse()->getContent();
            $canvasPos = strpos($html, 'id="pdf-picker-canvas"');
            $scriptPos = strpos($html, 'pdf.min.js');
            self::assertNotFalse($canvasPos);
            self::assertNotFalse($scriptPos);
            self::assertGreaterThan($canvasPos, $scriptPos, 'Picker script must load after the canvas markup.');

            $this->client->request('GET', '/admin/questionnaires/'.$questionnaire->id().'/pdf-template');
            self::assertResponseIsSuccessful();
            self::assertResponseHeaderSame('content-type', 'application/pdf');
        } finally {
            if ($created && is_file($templatePath)) {
                unlink($templatePath);
            }
        }
    }

    public function testTheMappingFormExplainsWhenTheTemplateIsMissing(): void
    {
        $this->loginAdmin();
        $questionnaire = $this->seedQuestionnaire();
        $templatesDir = static::getContainer()->getParameter('app.pdf.templates_dir');
        self::assertIsString($templatesDir);
        $templatePath = $templatesDir.DIRECTORY_SEPARATOR.'1040-nr.pdf';
        $backup = null;

        if (is_file($templatePath)) {
            $backup = $templatePath.'.bak-test';
            rename($templatePath, $backup);
        }

        try {
            $this->client->request('GET', '/admin/questionnaires/'.$questionnaire->id().'/mappings/new');
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('body', 'No blank PDF template is available');
            self::assertSelectorNotExists('#pdf-picker-canvas');

            $this->client->request('GET', '/admin/questionnaires/'.$questionnaire->id().'/pdf-template');
            self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        } finally {
            if ($backup !== null && is_file($backup)) {
                rename($backup, $templatePath);
            }
        }
    }

    private function seedQuestionnaire(): Questionnaire
    {
        $questionnaire = Questionnaire::create('q-picker', '1040-NR', FormType::Form1040Nr);
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

    private function hash(string $plainPassword): string
    {
        $hasher = static::getContainer()->get(PasswordHasherInterface::class);
        self::assertInstanceOf(PasswordHasherInterface::class, $hasher);

        return $hasher->hash($plainPassword);
    }
}
