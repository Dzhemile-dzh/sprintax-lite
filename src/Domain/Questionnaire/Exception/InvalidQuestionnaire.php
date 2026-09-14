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

    public static function structureLocked(): self
    {
        return new self('Questionnaire structure cannot be deleted after a client has started this questionnaire.');
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

    public static function mappingNotFound(string $mappingId): self
    {
        return new self(sprintf('PDF mapping "%s" does not belong to this questionnaire.', $mappingId));
    }

    public static function optionNotFound(string $optionId): self
    {
        return new self(sprintf('Option "%s" does not belong to this question.', $optionId));
    }

    public static function unknownVisibilityQuestion(string $key): self
    {
        return new self(sprintf('Visibility cannot depend on unknown question key "%s".', $key));
    }

    public static function questionReferenced(string $key): self
    {
        return new self(sprintf(
            'Question "%s" cannot be removed while other questions or PDF mappings still reference it.',
            $key,
        ));
    }

    public static function unknownComputedField(string $fieldName): self
    {
        return new self(sprintf('"%s" is not a computed field for this form type.', $fieldName));
    }

    public static function computedFieldsUnavailable(string $formType): self
    {
        return new self(sprintf('This form type ("%s") has no computed PDF fields.', $formType));
    }
}
