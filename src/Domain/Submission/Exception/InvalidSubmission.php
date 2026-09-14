<?php

declare(strict_types=1);

namespace App\Domain\Submission\Exception;

use App\Domain\Submission\ValueObject\SubmissionStatus;
use RuntimeException;

final class InvalidSubmission extends RuntimeException
{
    private bool $deniesAccess = false;

    public static function questionnaireHasNoSteps(): self
    {
        return new self('A submission cannot start because the questionnaire has no steps.');
    }

    public static function questionNotInQuestionnaire(): self
    {
        return new self('Cannot answer a question that does not belong to this submission questionnaire.');
    }

    public static function cannotFinalize(SubmissionStatus $status): self
    {
        return new self(sprintf('A submission cannot be finalized from status "%s".', $status->value));
    }

    public static function cannotMarkPdfReady(SubmissionStatus $status): self
    {
        return new self(sprintf('A PDF cannot be marked ready from status "%s".', $status->value));
    }

    public static function cannotGeneratePdf(SubmissionStatus $status): self
    {
        return new self(sprintf('A PDF cannot be generated from status "%s".', $status->value));
    }

    public static function unknownStep(string $stepId): self
    {
        return new self(sprintf('Step "%s" is not part of this submission questionnaire.', $stepId));
    }

    public static function cannotSkipAhead(): self
    {
        $exception = new self('Cannot skip ahead to a later wizard step.');
        $exception->deniesAccess = true;

        return $exception;
    }

    public function deniesAccess(): bool
    {
        return $this->deniesAccess;
    }

    public static function blankAnswerValue(): self
    {
        return new self('Answer values cannot be blank.');
    }

    public static function cannotEdit(SubmissionStatus $status): self
    {
        return new self(sprintf('A submission cannot be edited from status "%s".', $status->value));
    }

    public static function requiredAnswer(string $questionKey): self
    {
        return new self(sprintf('Question "%s" is required.', $questionKey));
    }

    public static function invalidAnswer(string $questionKey, string $reason): self
    {
        return new self(sprintf('Question "%s" is invalid: %s.', $questionKey, $reason));
    }

    public static function pdfNotReady(SubmissionStatus $status): self
    {
        return new self(sprintf('The PDF is not ready for download from status "%s".', $status->value));
    }

    public static function pdfFileMissing(): self
    {
        return new self('The generated PDF file is not available.');
    }

    public static function unsafePdfPath(): self
    {
        return new self('The PDF path is not valid for this submission.');
    }

    public static function notOwner(): self
    {
        $exception = new self('This submission does not belong to the current user.');
        $exception->deniesAccess = true;

        return $exception;
    }
}
