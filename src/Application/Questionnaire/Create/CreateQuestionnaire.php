<?php

declare(strict_types=1);

namespace App\Application\Questionnaire\Create;

use App\Application\Audit\RecordQuestionnaireRevision;
use App\Domain\Audit\ValueObject\RevisionAction;
use App\Domain\Questionnaire\Entity\Questionnaire;
use App\Domain\Questionnaire\Repository\QuestionnaireRepositoryInterface;
use App\Domain\Questionnaire\ValueObject\FormType;

final class CreateQuestionnaire
{
    public function __construct(
        private readonly QuestionnaireRepositoryInterface $questionnaires,
        private readonly RecordQuestionnaireRevision $revisions,
    ) {
    }

    public function execute(string $name, FormType $formType, ?string $description): Questionnaire
    {
        $questionnaire = Questionnaire::create(
            bin2hex(random_bytes(16)),
            $name,
            $formType,
            $description,
        );
        $this->questionnaires->save($questionnaire);
        $this->revisions->execute(
            $questionnaire,
            RevisionAction::QuestionnaireCreated,
            sprintf('Created "%s" for form type %s', $questionnaire->name(), $formType->value),
        );

        return $questionnaire;
    }
}
