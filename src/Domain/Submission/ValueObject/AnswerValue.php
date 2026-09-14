<?php

declare(strict_types=1);

namespace App\Domain\Submission\ValueObject;

use App\Domain\Submission\Exception\InvalidSubmission;

final readonly class AnswerValue
{
    /**
     * @param string|list<string> $value
     */
    private function __construct(
        private string|array $value,
    ) {
    }

    public static function text(string $value): self
    {
        return new self($value);
    }

    /**
     * @param list<string> $values
     */
    public static function choices(array $values): self
    {
        foreach ($values as $value) {
            if (trim($value) === '') {
                throw InvalidSubmission::blankAnswerValue();
            }
        }

        return new self(array_values($values));
    }

    /**
     * @return string|list<string>
     */
    public function raw(): string|array
    {
        return $this->value;
    }
}
