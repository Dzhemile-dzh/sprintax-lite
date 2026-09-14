<?php

declare(strict_types=1);

namespace App\Domain\Calculation\Exception;

use App\Domain\Questionnaire\ValueObject\FormType;
use RuntimeException;

final class UnsupportedFormType extends RuntimeException
{
    public static function for(FormType $formType): self
    {
        return new self(sprintf('No calculator is registered for form type "%s".', $formType->value));
    }
}
