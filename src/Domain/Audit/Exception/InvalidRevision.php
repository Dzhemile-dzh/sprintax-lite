<?php

declare(strict_types=1);

namespace App\Domain\Audit\Exception;

use DomainException;

final class InvalidRevision extends DomainException
{
    public static function blank(string $field): self
    {
        return new self(sprintf('A revision %s cannot be blank.', $field));
    }

    public static function invalidVersion(int $version): self
    {
        return new self(sprintf('Revision version %d must be at least 1.', $version));
    }
}
