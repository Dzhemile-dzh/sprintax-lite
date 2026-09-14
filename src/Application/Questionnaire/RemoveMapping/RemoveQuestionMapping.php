<?php

declare(strict_types=1);

namespace App\Application\Questionnaire\RemoveMapping;

use App\Domain\Questionnaire\Repository\QuestionnaireRepositoryInterface;

final class RemoveQuestionMapping
{
    public function __construct(
        private readonly QuestionnaireRepositoryInterface $questionnaires,
    ) {
    }

    public function execute(string $questionnaireId, string $mappingId): void
    {
        $questionnaire = $this->questionnaires->get($questionnaireId);
        $questionnaire->removeMapping($mappingId);
        $this->questionnaires->save($questionnaire);
    }
}
