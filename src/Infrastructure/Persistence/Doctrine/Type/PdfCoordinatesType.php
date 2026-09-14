<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\Questionnaire\ValueObject\PdfCoordinates;
use InvalidArgumentException;

/**
 * @extends JsonValueObjectType<PdfCoordinates>
 */
final class PdfCoordinatesType extends JsonValueObjectType
{
    public const NAME = 'pdf_coordinates';

    protected function valueClass(): string
    {
        return PdfCoordinates::class;
    }

    protected function fromArray(array $data): PdfCoordinates
    {
        $fontSize = $data['fontSize'] ?? null;

        if ($fontSize !== null && !is_int($fontSize)) {
            throw new InvalidArgumentException('Invalid fontSize in PDF coordinates.');
        }

        return new PdfCoordinates(
            $this->intValue($data['page'] ?? null, 'page'),
            $this->floatValue($data['xMm'] ?? null, 'xMm'),
            $this->floatValue($data['yMm'] ?? null, 'yMm'),
            $fontSize,
        );
    }

    protected function toArray(object $value): array
    {
        assert($value instanceof PdfCoordinates);

        return [
            'page' => $value->page,
            'xMm' => $value->xMm,
            'yMm' => $value->yMm,
            'fontSize' => $value->fontSize,
        ];
    }

    private function intValue(mixed $value, string $field): int
    {
        if (!is_int($value)) {
            throw new InvalidArgumentException(sprintf('Invalid %s in PDF coordinates.', $field));
        }

        return $value;
    }

    private function floatValue(mixed $value, string $field): float
    {
        if (!is_int($value) && !is_float($value)) {
            throw new InvalidArgumentException(sprintf('Invalid %s in PDF coordinates.', $field));
        }

        return (float) $value;
    }
}
