<?php

declare(strict_types=1);

namespace App\Domain\User\Exception;

use RuntimeException;

final class InvalidEmail extends RuntimeException
{
    public static function fromValue(string $value): self
    {
        return new self(sprintf('"%s" is not a valid email address.', $value));
    }
}
