<?php

declare(strict_types=1);

namespace App\Domain\Questionnaire\ValueObject;

enum VisibilityOperator: string
{
    case Equals = 'equals';
    case NotEquals = 'not_equals';
}
