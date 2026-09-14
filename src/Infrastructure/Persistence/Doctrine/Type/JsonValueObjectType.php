<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\JsonType;
use InvalidArgumentException;

/**
 * @template T of object
 */
abstract class JsonValueObjectType extends JsonType
{
    /**
     * @param array<mixed> $data
     * @return T
     */
    abstract protected function fromArray(array $data): object;

    /**
     * @param T $value
     * @return array<string, mixed>
     */
    abstract protected function toArray(object $value): array;

    /**
     * @return class-string<T>
     */
    abstract protected function valueClass(): string;

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return parent::convertToDatabaseValue(null, $platform);
        }

        $class = $this->valueClass();

        if (!$value instanceof $class) {
            throw new InvalidArgumentException(sprintf('Expected %s.', $class));
        }

        return parent::convertToDatabaseValue($this->toArray($value), $platform);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): mixed
    {
        $data = parent::convertToPHPValue($value, $platform);

        if ($data === null) {
            return null;
        }

        if (!is_array($data)) {
            throw new InvalidArgumentException(sprintf('Expected array when converting %s.', $this->valueClass()));
        }

        return $this->fromArray($data);
    }
}
