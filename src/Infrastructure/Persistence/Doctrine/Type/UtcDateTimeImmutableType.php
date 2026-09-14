<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\DateTimeImmutableType;
use Doctrine\DBAL\Types\Exception\InvalidFormat;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Exception;

final class UtcDateTimeImmutableType extends DateTimeImmutableType
{
    private static function utc(): DateTimeZone
    {
        return new DateTimeZone('UTC');
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof DateTimeImmutable) {
            throw InvalidType::new(
                $value,
                static::class,
                ['null', DateTimeImmutable::class],
            );
        }

        return $value->setTimezone(self::utc())->format($platform->getDateTimeFormatString());
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeImmutable) {
            return $value->setTimezone(self::utc());
        }

        if (!is_string($value)) {
            throw InvalidType::new(
                $value,
                static::class,
                ['null', 'string', DateTimeImmutable::class],
            );
        }

        $dateTime = DateTimeImmutable::createFromFormat(
            $platform->getDateTimeFormatString(),
            $value,
            self::utc(),
        );

        if ($dateTime !== false) {
            return $dateTime;
        }

        try {
            return (new DateTimeImmutable($value))->setTimezone(self::utc());
        } catch (Exception $exception) {
            throw InvalidFormat::new(
                $value,
                static::class,
                $platform->getDateTimeFormatString(),
                $exception,
            );
        }
    }
}
