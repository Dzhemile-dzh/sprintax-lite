<?php

declare(strict_types=1);

namespace App\Application\Questionnaire\AddMapping;

use App\Domain\Questionnaire\Entity\QuestionMapping;
use App\Domain\Questionnaire\Repository\QuestionnaireRepositoryInterface;
use App\Domain\Questionnaire\ValueObject\MappingSourceType;
use App\Domain\Questionnaire\ValueObject\PdfCoordinates;

final class AddQuestionMapping
{
    public function __construct(
        private readonly QuestionnaireRepositoryInterface $questionnaires,
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
        $coordinates = new PdfCoordinates($page, $xMm, $yMm, $fontSize);
        $mapping = $sourceType === MappingSourceType::Question
            ? QuestionMapping::forQuestion(bin2hex(random_bytes(16)), $sourceReference, $coordinates)
            : QuestionMapping::forComputedField(bin2hex(random_bytes(16)), $sourceReference, $coordinates);

        $questionnaire->addMapping($mapping);
        $this->questionnaires->save($questionnaire);

        return $mapping;
    }
}
