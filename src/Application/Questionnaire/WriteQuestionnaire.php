<?php

declare(strict_types=1);

namespace App\Application\Questionnaire;

use App\Application\Audit\RecordQuestionnaireRevision;
use App\Domain\Audit\ValueObject\RevisionAction;
use App\Domain\Questionnaire\Entity\Questionnaire;
use App\Domain\Questionnaire\Repository\QuestionnaireRepositoryInterface;
use App\Domain\Questionnaire\ValueObject\FormType;
use App\Domain\Submission\Repository\SubmissionRepositoryInterface;

final class WriteQuestionnaire
{
    public function __construct(
        private readonly QuestionnaireRepositoryInterface $questionnaires,
        private readonly SubmissionRepositoryInterface $submissions,
        private readonly RecordQuestionnaireRevision $revisions,
    ) {
    }

    public function create(string $name, FormType $formType, ?string $description): Questionnaire
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

    public function update(string $id, string $name, ?string $description, FormType $formType): void
    {
        $questionnaire = $this->questionnaires->get($id);
        $questionnaire->changeFormType(
            $formType,
            $this->submissions->existsForQuestionnaire($id),
        );
        $questionnaire->rename($name);
        $questionnaire->changeDescription($description);
        $this->questionnaires->save($questionnaire);
        $this->revisions->execute(
            $questionnaire,
            RevisionAction::QuestionnaireUpdated,
            sprintf('Renamed to "%s" with form type %s', $questionnaire->name(), $formType->value),
        );
    }
}
