<?php

declare(strict_types=1);

namespace App\Application\User\Register;

use App\Application\User\PasswordHasherInterface;
use App\Domain\User\Entity\User;
use App\Domain\User\Exception\EmailAlreadyRegistered;
use App\Domain\User\Exception\InvalidUser;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\ValueObject\Email;

final class RegisterClient
{
    public const MIN_PASSWORD_LENGTH = 8;

    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly PasswordHasherInterface $passwordHasher,
    ) {
    }

    public function execute(string $email, string $plainPassword): User
    {
        if (trim($plainPassword) === '') {
            throw InvalidUser::blankPassword();
        }

        if (strlen($plainPassword) < self::MIN_PASSWORD_LENGTH) {
            throw InvalidUser::passwordTooShort(self::MIN_PASSWORD_LENGTH);
        }

        $emailAddress = new Email($email);

        if ($this->users->findByEmail($emailAddress) !== null) {
            throw EmailAlreadyRegistered::for($emailAddress);
        }

        $user = User::registerClient(
            bin2hex(random_bytes(16)),
            $emailAddress,
            $this->passwordHasher->hash($plainPassword),
        );
        $this->users->save($user);

        return $user;
    }
}
