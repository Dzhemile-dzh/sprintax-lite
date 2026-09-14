<?php

declare(strict_types=1);

namespace App\Domain\Submission\Exception;

use RuntimeException;

final class SubmissionNotFound extends RuntimeException
{
    public static function withId(string $id): self
    {
        return new self(sprintf('Submission "%s" was not found.', $id));
    }
}
