<?php

declare(strict_types=1);

namespace App\Domain\User\Exception;

use RuntimeException;

final class InvalidUser extends RuntimeException
{
    public static function blankPasswordHash(): self
    {
        return new self('Password hash cannot be blank.');
    }

    public static function blankPassword(): self
    {
        return new self('Password cannot be blank.');
    }

    public static function passwordTooShort(int $minLength): self
    {
        return new self(sprintf('Password must be at least %d characters.', $minLength));
    }
}
