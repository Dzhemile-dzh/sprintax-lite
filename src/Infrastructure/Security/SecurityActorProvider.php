<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Application\Audit\ActorProvider;
use App\Domain\Audit\ValueObject\RevisionActor;
use Symfony\Bundle\SecurityBundle\Security;

final class SecurityActorProvider implements ActorProvider
{
    public function __construct(
        private readonly Security $security,
    ) {
    }

    public function currentActor(): RevisionActor
    {
        $user = $this->security->getUser();

        if (!$user instanceof SecurityUser) {
            return RevisionActor::system();
        }

        return RevisionActor::user($user->id(), $user->getUserIdentifier());
    }
}
