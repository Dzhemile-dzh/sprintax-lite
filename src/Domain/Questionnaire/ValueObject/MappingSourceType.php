<?php

declare(strict_types=1);

namespace App\Domain\Questionnaire\ValueObject;

enum MappingSourceType: string
{
    case Question = 'question';
    case ComputedField = 'computed_field';
}
