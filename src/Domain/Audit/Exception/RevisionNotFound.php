<?php

declare(strict_types=1);

namespace App\Domain\Audit\Exception;

use RuntimeException;

final class RevisionNotFound extends RuntimeException
{
    public static function version(string $questionnaireId, int $version): self
    {
        return new self(sprintf('Questionnaire "%s" has no revision %d.', $questionnaireId, $version));
    }
}
