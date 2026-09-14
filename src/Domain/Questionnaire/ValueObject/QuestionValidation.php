<?php

declare(strict_types=1);

namespace App\Domain\Questionnaire\ValueObject;

use App\Domain\Questionnaire\Exception\InvalidQuestionnaire;

final readonly class QuestionValidation
{
    public function __construct(
        public bool $required = false,
        public ?int $min = null,
        public ?int $max = null,
        public ?string $regex = null,
    ) {
        if ($this->min !== null && $this->max !== null && $this->min > $this->max) {
            throw InvalidQuestionnaire::invalidValidationRange();
        }

        if ($this->regex !== null && trim($this->regex) === '') {
            throw InvalidQuestionnaire::blank('validation regex');
        }

        if ($this->regex !== null && @preg_match(self::delimitedPattern($this->regex), '') === false) {
            throw InvalidQuestionnaire::invalidValidationRegex();
        }
    }

    public function matchesPattern(string $raw): bool
    {
        if ($this->regex === null) {
            return true;
        }

        return @preg_match(self::delimitedPattern($this->regex), $raw) === 1;
    }

    private static function delimitedPattern(string $regex): string
    {
        foreach (['/', '#', '~', '%', '@', '!', ';'] as $delimiter) {
            if (!str_contains($regex, $delimiter)) {
                return $delimiter.$regex.$delimiter.'u';
            }
        }

        return "\x01".$regex."\x01u";
    }

    public static function none(): self
    {
        return new self();
    }

    public static function required(): self
    {
        return new self(required: true);
    }
}
