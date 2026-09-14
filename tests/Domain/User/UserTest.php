<?php

declare(strict_types=1);

namespace App\Tests\Domain\User;

use App\Domain\User\Entity\User;
use App\Domain\User\Exception\InvalidEmail;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\ValueObject\UserRole;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testClientsSelfRegisterAndAdminsAreProvisioned(): void
    {
        $client = User::registerClient('u-1', new Email('Client@example.test'), 'hash');
        $admin = User::provisionAdmin('u-2', new Email('admin@example.test'), 'hash');

        self::assertSame('client@example.test', $client->email()->value());
        self::assertTrue($client->isClient());
        self::assertFalse($client->isAdmin());
        self::assertSame(UserRole::Admin, $admin->role());
        self::assertTrue($admin->isAdmin());
    }

    public function testInvalidEmailIsRejected(): void
    {
        $this->expectException(InvalidEmail::class);
        new Email('not-an-email');
    }

    public function testBlankEmailIsRejected(): void
    {
        $this->expectException(InvalidEmail::class);
        new Email('   ');
    }
}
