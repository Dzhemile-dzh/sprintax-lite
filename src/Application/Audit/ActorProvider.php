<?php

declare(strict_types=1);

namespace App\Application\Audit;

use App\Domain\Audit\ValueObject\RevisionActor;

/**
 * Port for "who is performing this change", so use cases keep their intent-shaped signatures.
 */
interface ActorProvider
{
    public function currentActor(): RevisionActor;
}
