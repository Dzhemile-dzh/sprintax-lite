<?php

declare(strict_types=1);

namespace App\Application\Questionnaire\Update;

use App\Domain\Questionnaire\Repository\QuestionnaireRepositoryInterface;
use App\Domain\Questionnaire\ValueObject\FormType;
use App\Domain\Submission\Repository\SubmissionRepositoryInterface;

final class UpdateQuestionnaire
{
    public function __construct(
        private readonly QuestionnaireRepositoryInterface $questionnaires,
        private readonly SubmissionRepositoryInterface $submissions,
    ) {
    }

    public function execute(string $id, string $name, ?string $description, FormType $formType): void
    {
        $questionnaire = $this->questionnaires->get($id);
        $questionnaire->changeFormType(
            $formType,
            $this->submissions->existsForQuestionnaire($id),
        );
        $questionnaire->rename($name);
        $questionnaire->changeDescription($description);
        $this->questionnaires->save($questionnaire);
    }
}
