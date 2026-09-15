<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Application\Audit\ActorProvider;
use App\Domain\Audit\ValueObject\RevisionActor;

final class FixedActorProvider implements ActorProvider
{
    public function __construct(
        private readonly RevisionActor $actor,
    ) {
    }

    public static function admin(): self
    {
        return new self(RevisionActor::user('admin-1', 'admin@example.test'));
    }

    public function currentActor(): RevisionActor
    {
        return $this->actor;
    }
}
