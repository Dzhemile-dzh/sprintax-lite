<?php

declare(strict_types=1);

namespace App\Domain\User\Entity;

use App\Domain\User\Exception\InvalidUser;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\ValueObject\UserRole;

final class User
{
    private function __construct(
        private string $id,
        private Email $email,
        private string $passwordHash,
        private UserRole $role,
    ) {
        if (trim($this->passwordHash) === '') {
            throw InvalidUser::blankPasswordHash();
        }
    }

    public static function registerClient(string $id, Email $email, string $passwordHash): self
    {
        return new self($id, $email, $passwordHash, UserRole::Client);
    }

    public static function provisionAdmin(string $id, Email $email, string $passwordHash): self
    {
        return new self($id, $email, $passwordHash, UserRole::Admin);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function email(): Email
    {
        return $this->email;
    }

    public function passwordHash(): string
    {
        return $this->passwordHash;
    }

    public function role(): UserRole
    {
        return $this->role;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isClient(): bool
    {
        return $this->role === UserRole::Client;
    }

    public function changePasswordHash(string $passwordHash): void
    {
        if (trim($passwordHash) === '') {
            throw InvalidUser::blankPasswordHash();
        }

        $this->passwordHash = $passwordHash;
    }
}
