<?php

declare(strict_types=1);

namespace App\Infrastructure\WebProfiler;

use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

#[When('dev')]
#[AsEventListener(event: KernelEvents::RESPONSE, priority: -114)]
final class RestrictWebDebugToolbarToAdmin
{
    public function __construct(
        private readonly AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if ($this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            return;
        }

        $event->getResponse()->headers->remove('X-Debug-Token');
        $event->getResponse()->headers->remove('X-Debug-Token-Link');
    }
}
