<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\WebProfiler;

use App\Infrastructure\WebProfiler\RestrictWebDebugToolbarToAdmin;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class RestrictWebDebugToolbarToAdminTest extends TestCase
{
    public function testItRemovesTheDebugTokenWhenTheUserIsNotAnAdmin(): void
    {
        $listener = new RestrictWebDebugToolbarToAdmin($this->authorization(false));
        $response = $this->debugResponse();
        $event = $this->mainRequestEvent($response);

        $listener($event);

        self::assertFalse($response->headers->has('X-Debug-Token'));
        self::assertFalse($response->headers->has('X-Debug-Token-Link'));
    }

    public function testItKeepsTheDebugTokenForAnAdmin(): void
    {
        $listener = new RestrictWebDebugToolbarToAdmin($this->authorization(true));
        $response = $this->debugResponse();
        $event = $this->mainRequestEvent($response);

        $listener($event);

        self::assertSame('abc123', $response->headers->get('X-Debug-Token'));
        self::assertSame('/_profiler/abc123', $response->headers->get('X-Debug-Token-Link'));
    }

    public function testItDoesNotChangeSubRequests(): void
    {
        $listener = new RestrictWebDebugToolbarToAdmin($this->authorization(false));
        $response = $this->debugResponse();
        $event = new ResponseEvent(
            $this->createStub(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::SUB_REQUEST,
            $response,
        );

        $listener($event);

        self::assertSame('abc123', $response->headers->get('X-Debug-Token'));
    }

    private function authorization(bool $isAdmin): AuthorizationCheckerInterface
    {
        $authorization = $this->createStub(AuthorizationCheckerInterface::class);
        $authorization->method('isGranted')->willReturn($isAdmin);

        return $authorization;
    }

    private function debugResponse(): Response
    {
        $response = new Response();
        $response->headers->set('X-Debug-Token', 'abc123');
        $response->headers->set('X-Debug-Token-Link', '/_profiler/abc123');

        return $response;
    }

    private function mainRequestEvent(Response $response): ResponseEvent
    {
        return new ResponseEvent(
            $this->createStub(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            $response,
        );
    }
}
