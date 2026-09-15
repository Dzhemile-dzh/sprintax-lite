<?php

declare(strict_types=1);

namespace App\Application\Questionnaire\AddMapping;

use App\Application\Audit\RecordQuestionnaireRevision;
use App\Application\Calculation\ListCalculatorFields;
use App\Domain\Audit\ValueObject\RevisionAction;
use App\Domain\Questionnaire\Entity\QuestionMapping;
use App\Domain\Questionnaire\Exception\InvalidQuestionnaire;
use App\Domain\Questionnaire\Repository\QuestionnaireRepositoryInterface;
use App\Domain\Questionnaire\ValueObject\FormType;
use App\Domain\Questionnaire\ValueObject\MappingSourceType;
use App\Domain\Questionnaire\ValueObject\PdfCoordinates;

final class AddQuestionMapping
{
    public function __construct(
        private readonly QuestionnaireRepositoryInterface $questionnaires,
        private readonly ListCalculatorFields $calculatorFields,
        private readonly RecordQuestionnaireRevision $revisions,
    ) {
    }

    public function execute(
        string $questionnaireId,
        MappingSourceType $sourceType,
        string $sourceReference,
        int $page,
        float $xMm,
        float $yMm,
        ?int $fontSize,
    ): QuestionMapping {
        $questionnaire = $this->questionnaires->get($questionnaireId);

        if ($sourceType === MappingSourceType::ComputedField) {
            $this->assertComputedField($questionnaire->formType(), $sourceReference);
        }

        $coordinates = new PdfCoordinates($page, $xMm, $yMm, $fontSize);
        $mapping = $sourceType === MappingSourceType::Question
            ? QuestionMapping::forQuestion(bin2hex(random_bytes(16)), $sourceReference, $coordinates)
            : QuestionMapping::forComputedField(bin2hex(random_bytes(16)), $sourceReference, $coordinates);

        $questionnaire->addMapping($mapping);
        $this->questionnaires->save($questionnaire);
        $this->revisions->execute(
            $questionnaire,
            RevisionAction::MappingAdded,
            sprintf(
                'Mapped %s "%s" to page %d at %gmm, %gmm',
                $sourceType->value,
                $sourceReference,
                $page,
                $xMm,
                $yMm,
            ),
        );

        return $mapping;
    }

    private function assertComputedField(FormType $formType, string $fieldName): void
    {
        $fields = $this->calculatorFields->execute($formType);

        if ($fields === []) {
            throw InvalidQuestionnaire::computedFieldsUnavailable($formType->value);
        }

        if (!in_array($fieldName, $fields, true)) {
            throw InvalidQuestionnaire::unknownComputedField($fieldName);
        }
    }
}
