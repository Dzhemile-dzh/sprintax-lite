<?php

declare(strict_types=1);

namespace App\Domain\Questionnaire\Exception;

use RuntimeException;

final class QuestionnaireNotFound extends RuntimeException
{
    public static function withId(string $id): self
    {
        return new self(sprintf('Questionnaire "%s" was not found.', $id));
    }
}
