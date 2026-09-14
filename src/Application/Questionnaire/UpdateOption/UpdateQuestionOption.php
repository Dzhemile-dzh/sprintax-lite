<?php

declare(strict_types=1);

namespace App\Application\Questionnaire\UpdateOption;

use App\Domain\Questionnaire\Repository\QuestionnaireRepositoryInterface;

final class UpdateQuestionOption
{
    public function __construct(
        private readonly QuestionnaireRepositoryInterface $questionnaires,
    ) {
    }

    public function execute(
        string $questionnaireId,
        string $questionId,
        string $optionId,
        string $label,
        string $value,
    ): void {
        $questionnaire = $this->questionnaires->get($questionnaireId);
        $questionnaire->updateOption($questionId, $optionId, $label, $value);
        $this->questionnaires->save($questionnaire);
    }
}
