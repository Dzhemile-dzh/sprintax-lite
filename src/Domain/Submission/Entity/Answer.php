<?php

declare(strict_types=1);

namespace App\Domain\Submission\Entity;

use App\Domain\Questionnaire\Entity\Question;
use App\Domain\Submission\ValueObject\AnswerValue;

final class Answer
{
    public function __construct(
        private Question $question,
        private AnswerValue $value,
    ) {
    }

    public function question(): Question
    {
        return $this->question;
    }

    public function value(): AnswerValue
    {
        return $this->value;
    }

    public function replaceValue(AnswerValue $value): void
    {
        $this->value = $value;
    }
}
