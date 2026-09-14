<?php

declare(strict_types=1);

namespace App\Domain\User\Exception;

use App\Domain\User\ValueObject\Email;
use RuntimeException;

final class EmailAlreadyRegistered extends RuntimeException
{
    public static function for(Email $email): self
    {
        return new self(sprintf('Email "%s" is already registered.', $email->value()));
    }
}
