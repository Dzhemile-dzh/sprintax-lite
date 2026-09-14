<?php

declare(strict_types=1);

namespace App\Tests\Domain\Submission;

use App\Domain\Questionnaire\Entity\Question;
use App\Domain\Questionnaire\Entity\QuestionOption;
use App\Domain\Questionnaire\Entity\Questionnaire;
use App\Domain\Questionnaire\ValueObject\FormType;
use App\Domain\Questionnaire\ValueObject\QuestionType;
use App\Domain\Questionnaire\ValueObject\QuestionValidation;
use App\Domain\Submission\Exception\InvalidSubmission;
use App\Domain\Submission\QuestionAnswerValidator;
use App\Domain\Submission\ValueObject\AnswerValue;
use PHPUnit\Framework\TestCase;

final class QuestionAnswerValidatorTest extends TestCase
{
    public function testARequiredBlankAnswerIsRejected(): void
    {
        $this->expectException(InvalidSubmission::class);
        $this->expectExceptionMessage('Question "first_name" is required.');

        $this->validator()->validate(
            $this->question('first_name', QuestionType::ShortText, QuestionValidation::required()),
            AnswerValue::text(''),
        );
    }

    public function testAnOptionalBlankAnswerIsAllowed(): void
    {
        $this->validator()->validate(
            $this->question('notes', QuestionType::ShortText),
            AnswerValue::text(''),
        );

        $this->addToAssertionCount(1);
    }

    public function testYesNoMustBeYesOrNo(): void
    {
        $this->expectException(InvalidSubmission::class);
        $this->expectExceptionMessage('must be yes or no');

        $this->validator()->validate(
            $this->question('married', QuestionType::YesNo, QuestionValidation::required()),
            AnswerValue::text('maybe'),
        );
    }

    public function testDatesMustBeIsoFormatted(): void
    {
        $this->expectException(InvalidSubmission::class);
        $this->expectExceptionMessage('must be a valid date');

        $this->validator()->validate(
            $this->question('birth_date', QuestionType::Date),
            AnswerValue::text('01-05-1990'),
        );
    }

    public function testNumbersMustBeNumericAndInsideBounds(): void
    {
        $question = $this->question(
            'income_wages',
            QuestionType::Number,
            new QuestionValidation(required: true, min: 0, max: 100),
        );

        $this->validator()->validate($question, AnswerValue::text('40'));

        try {
            $this->validator()->validate($question, AnswerValue::text('abc'));
            self::fail('Expected a non-numeric wage to be rejected.');
        } catch (InvalidSubmission $exception) {
            self::assertStringContainsString('must be a number', $exception->getMessage());
        }

        try {
            $this->validator()->validate($question, AnswerValue::text('-1'));
            self::fail('Expected a wage below the minimum to be rejected.');
        } catch (InvalidSubmission $exception) {
            self::assertStringContainsString('is below the minimum', $exception->getMessage());
        }

        try {
            $this->validator()->validate($question, AnswerValue::text('101'));
            self::fail('Expected a wage above the maximum to be rejected.');
        } catch (InvalidSubmission $exception) {
            self::assertStringContainsString('is above the maximum', $exception->getMessage());
        }
    }

    public function testChoiceAnswersMustMatchConfiguredOptions(): void
    {
        $residency = $this->choiceQuestion('residency', QuestionType::SingleChoice, ['resident', 'nonresident']);

        $this->validator()->validate($residency, AnswerValue::text('resident'));

        $this->expectException(InvalidSubmission::class);
        $this->expectExceptionMessage('value is not a valid option');
        $this->validator()->validate($residency, AnswerValue::text('citizen'));
    }

    public function testMultiChoiceAnswersRejectUnknownValues(): void
    {
        $incomeTypes = $this->choiceQuestion('income_types', QuestionType::MultiChoice, ['wages', 'treaty']);

        $this->validator()->validate($incomeTypes, AnswerValue::choices(['wages']));

        $this->expectException(InvalidSubmission::class);
        $this->expectExceptionMessage('value is not a valid option');
        $this->validator()->validate($incomeTypes, AnswerValue::choices(['wages', 'lottery']));
    }

    public function testRegexRulesRejectNonMatchingText(): void
    {
        $question = $this->question(
            'code',
            QuestionType::ShortText,
            new QuestionValidation(regex: '^[A-Z]{3}$'),
        );

        $this->validator()->validate($question, AnswerValue::text('ABC'));

        $this->expectException(InvalidSubmission::class);
        $this->expectExceptionMessage('does not match the expected format');
        $this->validator()->validate($question, AnswerValue::text('ab'));
    }

    public function testRegexRulesAllowASlashWithoutDelimiterCollision(): void
    {
        $question = $this->question(
            'code',
            QuestionType::ShortText,
            new QuestionValidation(regex: '^[A-Z]/[0-9]$'),
        );

        $this->validator()->validate($question, AnswerValue::text('A/1'));

        $this->expectException(InvalidSubmission::class);
        $this->expectExceptionMessage('does not match the expected format');
        $this->validator()->validate($question, AnswerValue::text('A1'));
    }

    private function validator(): QuestionAnswerValidator
    {
        return new QuestionAnswerValidator();
    }

    private function question(
        string $key,
        QuestionType $type,
        ?QuestionValidation $validation = null,
    ): Question {
        $questionnaire = Questionnaire::create('q-1', '1040-NR', FormType::Form1040Nr);
        $questionnaire->addStep('step-1', 'Personal');

        return $questionnaire->addQuestion('step-1', 'id-'.$key, $key, ucfirst($key), $type, null, $validation);
    }

    /**
     * @param list<string> $values
     */
    private function choiceQuestion(string $key, QuestionType $type, array $values): Question
    {
        $question = $this->question($key, $type, QuestionValidation::required());
        $position = 1;

        foreach ($values as $value) {
            $question->addOption(QuestionOption::create('opt-'.$value, ucfirst($value), $value, $position));
            ++$position;
        }

        return $question;
    }
}
