<?php

declare(strict_types=1);

namespace App\Domain\Questionnaire\ValueObject;

enum QuestionType: string
{
    case ShortText = 'short_text';
    case Number = 'number';
    case Date = 'date';
    case SingleChoice = 'single_choice';
    case MultiChoice = 'multi_choice';
    case YesNo = 'yes_no';

    public function allowsOptions(): bool
    {
        return $this === self::SingleChoice || $this === self::MultiChoice;
    }
}
