<?php

declare(strict_types=1);

namespace App\Tests\Domain\Questionnaire;

use App\Domain\Questionnaire\Exception\InvalidQuestionnaire;
use App\Domain\Questionnaire\ValueObject\QuestionValidation;
use PHPUnit\Framework\TestCase;

final class QuestionValidationTest extends TestCase
{
    public function testAnUncompilableRegexIsRejected(): void
    {
        $this->expectException(InvalidQuestionnaire::class);
        $this->expectExceptionMessage('Validation regex is not a valid pattern.');

        new QuestionValidation(regex: '(unclosed');
    }

    public function testABlankRegexIsRejected(): void
    {
        $this->expectException(InvalidQuestionnaire::class);
        $this->expectExceptionMessage('validation regex');

        new QuestionValidation(regex: '   ');
    }
}
