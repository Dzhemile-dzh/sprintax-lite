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
}
