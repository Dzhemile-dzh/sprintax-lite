<?php

declare(strict_types=1);

namespace App\Domain\User\Entity;

use App\Domain\User\Exception\InvalidUser;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\ValueObject\UserRole;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'app_user')]
#[ORM\UniqueConstraint(name: 'uniq_app_user_email', columns: ['email'])]
final class User
{
    #[ORM\Id]
    #[ORM\Column(length: 64)]
    private string $id;

    #[ORM\Column(type: 'email', length: 180)]
    private Email $email;

    #[ORM\Column(name: 'password_hash', length: 255)]
    private string $passwordHash;

    #[ORM\Column(enumType: UserRole::class, length: 32)]
    private UserRole $role;

    private function __construct(
        string $id,
        Email $email,
        string $passwordHash,
        UserRole $role,
    ) {
        if (trim($passwordHash) === '') {
            throw InvalidUser::blankPasswordHash();
        }

        $this->id = $id;
        $this->email = $email;
        $this->passwordHash = $passwordHash;
        $this->role = $role;
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
