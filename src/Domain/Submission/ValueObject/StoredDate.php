<?php

declare(strict_types=1);

namespace App\Domain\Submission\ValueObject;

use DateTimeImmutable;
use DateTimeInterface;

final readonly class StoredDate
{
    private const STORAGE_FORMAT = 'Y-m-d';

    private function __construct(
        private DateTimeImmutable $value,
    ) {
    }

    public static function tryFrom(string $raw): ?self
    {
        $date = DateTimeImmutable::createFromFormat('!'.self::STORAGE_FORMAT, $raw);

        if (!$date instanceof DateTimeImmutable || $date->format(self::STORAGE_FORMAT) !== $raw) {
            return null;
        }

        return new self($date);
    }

    public static function fromDateTime(DateTimeInterface $date): self
    {
        return new self(DateTimeImmutable::createFromInterface($date)->setTime(0, 0));
    }

    public function toDateTime(): DateTimeImmutable
    {
        return $this->value;
    }

    public function toStorage(): string
    {
        return $this->value->format(self::STORAGE_FORMAT);
    }

    public function formatMonthDayYear(): string
    {
        return $this->value->format('m/d/Y');
    }
}
