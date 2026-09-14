<?php

declare(strict_types=1);

namespace App\Domain\Questionnaire\ValueObject;

enum FormType: string
{
    case Form1040Nr = '1040-nr';
    case FormW8Ben = 'w-8ben';

    public function label(): string
    {
        return match ($this) {
            self::Form1040Nr => 'Form 1040-NR',
            self::FormW8Ben => 'Form W-8BEN',
        };
    }

    public function isImplemented(): bool
    {
        return match ($this) {
            self::Form1040Nr => true,
            self::FormW8Ben => false,
        };
    }
}
