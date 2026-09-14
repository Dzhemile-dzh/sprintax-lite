<?php

declare(strict_types=1);

namespace App\Application\Questionnaire\AddQuestion;

use App\Domain\Questionnaire\Entity\Question;
use App\Domain\Questionnaire\Repository\QuestionnaireRepositoryInterface;
use App\Domain\Questionnaire\ValueObject\QuestionType;
use App\Domain\Questionnaire\ValueObject\QuestionValidation;
use App\Domain\Questionnaire\ValueObject\VisibilityRule;

final class AddQuestionnaireQuestion
{
    public function __construct(
        private readonly QuestionnaireRepositoryInterface $questionnaires,
    ) {
    }

    public function execute(
        string $questionnaireId,
        string $stepId,
        string $key,
        string $label,
        QuestionType $type,
        ?string $helpText,
        QuestionValidation $validation,
        VisibilityRule $visibility,
    ): Question {
        $questionnaire = $this->questionnaires->get($questionnaireId);
        $question = $questionnaire->addQuestion(
            $stepId,
            bin2hex(random_bytes(16)),
            $key,
            $label,
            $type,
            $helpText,
            $validation,
            $visibility,
        );
        $this->questionnaires->save($questionnaire);

        return $question;
    }
}
