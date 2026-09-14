<?php

declare(strict_types=1);

namespace App\Application\Questionnaire\AddOption;

use App\Domain\Questionnaire\Entity\QuestionOption;
use App\Domain\Questionnaire\Exception\InvalidQuestionnaire;
use App\Domain\Questionnaire\Repository\QuestionnaireRepositoryInterface;

final class AddQuestionOption
{
    public function __construct(
        private readonly QuestionnaireRepositoryInterface $questionnaires,
    ) {
    }

    public function execute(
        string $questionnaireId,
        string $questionId,
        string $label,
        string $value,
    ): QuestionOption {
        $questionnaire = $this->questionnaires->get($questionnaireId);
        $question = $questionnaire->findQuestion($questionId);

        if ($question === null) {
            throw InvalidQuestionnaire::questionNotFound($questionId);
        }

        $option = QuestionOption::create(
            bin2hex(random_bytes(16)),
            $label,
            $value,
            count($question->options()) + 1,
        );
        $question->addOption($option);
        $this->questionnaires->save($questionnaire);

        return $option;
    }
}
