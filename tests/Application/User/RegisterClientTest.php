<?php

declare(strict_types=1);

namespace App\Tests\Application\User;

use App\Application\User\PasswordHasherInterface;
use App\Application\User\Register\RegisterClient;
use App\Domain\User\Entity\User;
use App\Domain\User\Exception\EmailAlreadyRegistered;
use App\Domain\User\Exception\InvalidUser;
use App\Domain\User\Exception\UserNotFound;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\ValueObject\Email;
use PHPUnit\Framework\TestCase;

final class RegisterClientTest extends TestCase
{
    public function testItRegistersAClientWithAHashedPassword(): void
    {
        $users = new InMemoryUserRepository();
        $useCase = new RegisterClient($users, new PrefixPasswordHasher());

        $user = $useCase->execute('Client@example.test', 'password1');

        self::assertTrue($user->isClient());
        self::assertFalse($user->isAdmin());
        self::assertSame('client@example.test', $user->email()->value());
        self::assertSame('hashed-password1', $user->passwordHash());
        self::assertNotSame('', $user->id());
    }

    public function testItRejectsADuplicateEmail(): void
    {
        $users = new InMemoryUserRepository();
        $useCase = new RegisterClient($users, new PrefixPasswordHasher());
        $useCase->execute('client@example.test', 'password1');

        $this->expectException(EmailAlreadyRegistered::class);
        $useCase->execute('CLIENT@example.test', 'password2');
    }

    public function testItRejectsAShortPassword(): void
    {
        $useCase = new RegisterClient(new InMemoryUserRepository(), new PrefixPasswordHasher());

        $this->expectException(InvalidUser::class);
        $useCase->execute('client@example.test', 'short');
    }
}

final class PrefixPasswordHasher implements PasswordHasherInterface
{
    public function hash(string $plainPassword): string
    {
        return 'hashed-'.$plainPassword;
    }
}

final class InMemoryUserRepository implements UserRepositoryInterface
{
    /**
     * @param array<string, User> $users
     */
    public function __construct(
        private array $users = [],
    ) {
    }

    public function get(string $id): User
    {
        $user = $this->users[$id] ?? null;

        if (!$user instanceof User) {
            throw UserNotFound::withId($id);
        }

        return $user;
    }

    public function findByEmail(Email $email): ?User
    {
        foreach ($this->users as $user) {
            if ($user->email()->value() === $email->value()) {
                return $user;
            }
        }

        return null;
    }

    public function save(User $user): void
    {
        $this->users[$user->id()] = $user;
    }
}
