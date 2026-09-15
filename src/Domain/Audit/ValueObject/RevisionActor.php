<?php

declare(strict_types=1);

namespace App\Domain\Audit\ValueObject;

use App\Domain\Audit\Exception\InvalidRevision;

/**
 * The actor is stored as a snapshot of id and email so the trail survives user changes.
 */
final readonly class RevisionActor
{
    private function __construct(
        public ?string $id,
        public ?string $email,
    ) {
    }

    public static function user(string $id, string $email): self
    {
        if (trim($id) === '') {
            throw InvalidRevision::blank('actor id');
        }

        if (trim($email) === '') {
            throw InvalidRevision::blank('actor email');
        }

        return new self($id, $email);
    }

    public static function system(): self
    {
        return new self(null, null);
    }

    public function describe(): string
    {
        return $this->email ?? 'system';
    }
}
