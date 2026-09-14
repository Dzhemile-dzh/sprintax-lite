<?php

declare(strict_types=1);

namespace App\Domain\User\ValueObject;

use App\Domain\User\Exception\InvalidEmail;

final readonly class Email
{
    /** @var non-empty-string */
    public string $value;

    public function __construct(string $value)
    {
        $normalized = strtolower(trim($value));

        if ($normalized === '' || filter_var($normalized, FILTER_VALIDATE_EMAIL) === false) {
            throw InvalidEmail::fromValue($value);
        }

        $this->value = $normalized;
    }

    /**
     * @return non-empty-string
     */
    public function value(): string
    {
        return $this->value;
    }
}
