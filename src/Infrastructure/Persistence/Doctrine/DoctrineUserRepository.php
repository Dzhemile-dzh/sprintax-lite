<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Domain\User\Entity\User;
use App\Domain\User\Exception\UserNotFound;
use App\Domain\User\Repository\UserRepositoryInterface;
use LogicException;

final class DoctrineUserRepository implements UserRepositoryInterface
{
    public function get(string $id): User
    {
        throw UserNotFound::withId($id);
    }

    public function save(User $user): void
    {
        throw new LogicException(sprintf(
            'Doctrine persistence is not implemented yet for %s.',
            $user::class,
        ));
    }
}
