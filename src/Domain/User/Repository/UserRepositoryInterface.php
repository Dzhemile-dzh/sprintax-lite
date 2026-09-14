<?php

declare(strict_types=1);

namespace App\Domain\User\Repository;

use App\Domain\User\Entity\User;
use App\Domain\User\ValueObject\Email;

interface UserRepositoryInterface
{
    public function get(string $id): User;

    public function findByEmail(Email $email): ?User;

    public function save(User $user): void;
}
