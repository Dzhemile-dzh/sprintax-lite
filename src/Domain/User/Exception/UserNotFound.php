<?php

declare(strict_types=1);

namespace App\Domain\User\Exception;

use RuntimeException;

final class UserNotFound extends RuntimeException
{
    public static function withId(string $id): self
    {
        return new self(sprintf('User "%s" was not found.', $id));
    }
}
