<?php

declare(strict_types=1);

namespace App\Application\Questionnaire\Get;

use App\Domain\Questionnaire\Repository\QuestionnaireRepositoryInterface;
use App\Domain\Submission\Repository\SubmissionRepositoryInterface;

final class GetQuestionnaire
{
    public function __construct(
        private readonly QuestionnaireRepositoryInterface $questionnaires,
        private readonly SubmissionRepositoryInterface $submissions,
    ) {
    }

    public function execute(string $id): LoadedQuestionnaire
    {
        return new LoadedQuestionnaire(
            $this->questionnaires->get($id),
            $this->submissions->existsForQuestionnaire($id),
        );
    }
}
