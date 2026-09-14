<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Domain\User\Entity\User;
use App\Domain\User\ValueObject\UserRole;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class SecurityUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    /**
     * @param list<string> $roles
     */
    private function __construct(
        private readonly string $id,
        private readonly string $email,
        private readonly string $passwordHash,
        private readonly array $roles,
    ) {
    }

    public static function fromUser(User $user): self
    {
        return new self(
            $user->id(),
            $user->email()->value(),
            $user->passwordHash(),
            [$user->role()->value],
        );
    }

    public function id(): string
    {
        return $this->id;
    }

    public function isAdmin(): bool
    {
        return in_array(UserRole::Admin->value, $this->roles, true);
    }

    public function isClient(): bool
    {
        return in_array(UserRole::Client->value, $this->roles, true);
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        return $this->roles;
    }

    public function getPassword(): string
    {
        return $this->passwordHash;
    }

    public function eraseCredentials(): void
    {
    }
}
