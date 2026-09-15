<?php

declare(strict_types=1);

namespace App\Application\Questionnaire\AddQuestion;

use App\Application\Audit\RecordQuestionnaireRevision;
use App\Application\Questionnaire\QuestionFormMapping;
use App\Domain\Audit\ValueObject\RevisionAction;
use App\Domain\Questionnaire\Entity\Question;
use App\Domain\Questionnaire\Repository\QuestionnaireRepositoryInterface;
use App\Domain\Questionnaire\ValueObject\QuestionType;

final class AddQuestionnaireQuestion
{
    public function __construct(
        private readonly QuestionnaireRepositoryInterface $questionnaires,
        private readonly RecordQuestionnaireRevision $revisions,
    ) {
    }

    public function execute(
        string $questionnaireId,
        string $stepId,
        string $key,
        string $label,
        QuestionType $type,
        ?string $helpText,
        bool $required,
        mixed $min,
        mixed $max,
        mixed $regex,
        mixed $visibilityQuestionKey,
        mixed $visibilityOperator,
        mixed $visibilityExpectedValue,
    ): Question {
        $questionnaire = $this->questionnaires->get($questionnaireId);
        $question = $questionnaire->addQuestion(
            $stepId,
            bin2hex(random_bytes(16)),
            $key,
            $label,
            $type,
            $helpText,
            QuestionFormMapping::validation($required, $min, $max, $regex),
            QuestionFormMapping::visibility($visibilityQuestionKey, $visibilityOperator, $visibilityExpectedValue),
        );
        $this->questionnaires->save($questionnaire);
        $this->revisions->execute(
            $questionnaire,
            RevisionAction::QuestionAdded,
            sprintf('Added %s question "%s" to step "%s"', $type->value, $key, $question->step()->title()),
        );

        return $question;
    }
}
