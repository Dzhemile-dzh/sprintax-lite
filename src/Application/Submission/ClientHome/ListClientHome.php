<?php

declare(strict_types=1);

namespace App\Application\Submission\ClientHome;

use App\Domain\Questionnaire\Repository\QuestionnaireRepositoryInterface;
use App\Domain\Submission\Repository\SubmissionRepositoryInterface;

final class ListClientHome
{
    public function __construct(
        private readonly QuestionnaireRepositoryInterface $questionnaires,
        private readonly SubmissionRepositoryInterface $submissions,
    ) {
    }

    public function execute(string $userId): ClientHome
    {
        $submissionsByQuestionnaireId = [];

        foreach ($this->submissions->findForUser($userId) as $submission) {
            $submissionsByQuestionnaireId[$submission->questionnaire()->id()] = $submission;
        }

        return new ClientHome($this->questionnaires->all(), $submissionsByQuestionnaireId);
    }
}
