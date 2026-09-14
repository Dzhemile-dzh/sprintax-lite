<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Domain\User\Exception\InvalidEmail;
use App\Domain\User\Exception\UserNotFound;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\ValueObject\Email;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * @implements UserProviderInterface<SecurityUser>
 */
final class UserProvider implements UserProviderInterface, PasswordUpgraderInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        try {
            $email = new Email($identifier);
        } catch (InvalidEmail) {
            throw new UserNotFoundException();
        }

        $user = $this->users->findByEmail($email);

        if ($user === null) {
            throw new UserNotFoundException();
        }

        return SecurityUser::fromUser($user);
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof SecurityUser) {
            throw new UnsupportedUserException(sprintf('Unsupported user class "%s".', $user::class));
        }

        try {
            return SecurityUser::fromUser($this->users->get($user->id()));
        } catch (UserNotFound) {
            throw new UserNotFoundException();
        }
    }

    public function supportsClass(string $class): bool
    {
        return $class === SecurityUser::class || is_subclass_of($class, SecurityUser::class);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof SecurityUser) {
            return;
        }

        $domainUser = $this->users->get($user->id());
        $domainUser->changePasswordHash($newHashedPassword);
        $this->users->save($domainUser);
    }
}
