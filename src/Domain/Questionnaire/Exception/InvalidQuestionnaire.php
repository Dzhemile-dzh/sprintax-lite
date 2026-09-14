<?php

declare(strict_types=1);

namespace App\Domain\Questionnaire\Exception;

use RuntimeException;

final class InvalidQuestionnaire extends RuntimeException
{
    public static function blank(string $field): self
    {
        return new self(sprintf('Questionnaire %s cannot be blank.', $field));
    }

    public static function formTypeLocked(): self
    {
        return new self('Form type cannot be changed after a client has started this questionnaire.');
    }

    public static function duplicateQuestionKey(string $key): self
    {
        return new self(sprintf('Question key "%s" is already used in this questionnaire.', $key));
    }

    public static function stepNotFound(string $stepId): self
    {
        return new self(sprintf('Step "%s" does not belong to this questionnaire.', $stepId));
    }

    public static function questionNotFound(string $questionId): self
    {
        return new self(sprintf('Question "%s" does not belong to this questionnaire.', $questionId));
    }

    public static function optionsNotAllowed(string $type): self
    {
        return new self(sprintf('Question type "%s" does not allow options.', $type));
    }

    public static function duplicateOptionValue(string $value): self
    {
        return new self(sprintf('Option value "%s" is already used on this question.', $value));
    }

    public static function duplicateMappingSource(string $reference): self
    {
        return new self(sprintf('A PDF mapping for "%s" already exists on this questionnaire.', $reference));
    }

    public static function invalidPdfCoordinates(string $reason): self
    {
        return new self(sprintf('Invalid PDF mapping coordinates: %s.', $reason));
    }

    public static function invalidValidationRange(): self
    {
        return new self('Validation min cannot be greater than max.');
    }
}
