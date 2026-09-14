<?php

declare(strict_types=1);

namespace App\Tests\Application;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class KernelBootTest extends WebTestCase
{
    public function testKernelBoots(): void
    {
        self::bootKernel();

        self::assertSame('test', self::$kernel?->getEnvironment());
        self::assertTrue(self::getContainer()->has('router'));
        self::assertTrue(self::getContainer()->has('doctrine'));
    }

    public function testHomepageResponds(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        $statusCode = $client->getResponse()->getStatusCode();

        self::assertContains(
            $statusCode,
            [Response::HTTP_OK, Response::HTTP_NOT_FOUND, Response::HTTP_FOUND],
            'The application must boot and handle an HTTP request without a 500.',
        );
    }
}
