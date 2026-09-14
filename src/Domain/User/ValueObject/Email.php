<?php

declare(strict_types=1);

namespace App\Domain\User\ValueObject;

use App\Domain\User\Exception\InvalidEmail;

final readonly class Email
{
    public string $value;

    public function __construct(string $value)
    {
        $normalized = strtolower(trim($value));

        if (filter_var($normalized, FILTER_VALIDATE_EMAIL) === false) {
            throw InvalidEmail::fromValue($value);
        }

        $this->value = $normalized;
    }

    public function value(): string
    {
        return $this->value;
    }
}
