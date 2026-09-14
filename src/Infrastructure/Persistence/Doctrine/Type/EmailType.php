<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\User\ValueObject\Email;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\StringType;
use InvalidArgumentException;

final class EmailType extends StringType
{
    public const NAME = 'email';

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): mixed
    {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof Email) {
            throw new InvalidArgumentException('Expected Email.');
        }

        return $value->value();
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Email
    {
        $converted = parent::convertToPHPValue($value, $platform);

        if ($converted === null || $converted === '') {
            return null;
        }

        if (!is_string($converted)) {
            throw new InvalidArgumentException('Expected string when converting Email.');
        }

        return new Email($converted);
    }
}
