<?php

declare(strict_types=1);

namespace App\Application\Questionnaire\UpdateMapping;

use App\Domain\Questionnaire\Repository\QuestionnaireRepositoryInterface;
use App\Domain\Questionnaire\ValueObject\PdfCoordinates;

final class UpdateQuestionMapping
{
    public function __construct(
        private readonly QuestionnaireRepositoryInterface $questionnaires,
    ) {
    }

    public function execute(
        string $questionnaireId,
        string $mappingId,
        int $page,
        float $xMm,
        float $yMm,
        ?int $fontSize,
    ): void {
        $questionnaire = $this->questionnaires->get($questionnaireId);
        $questionnaire->relocateMapping($mappingId, new PdfCoordinates($page, $xMm, $yMm, $fontSize));
        $this->questionnaires->save($questionnaire);
    }
}
