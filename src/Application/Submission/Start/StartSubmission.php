<?php

declare(strict_types=1);

namespace App\Application\Submission\Start;

use App\Domain\Questionnaire\Repository\QuestionnaireRepositoryInterface;
use App\Domain\Submission\Entity\QuestionnaireSubmission;
use App\Domain\Submission\Repository\SubmissionRepositoryInterface;
use App\Domain\User\Repository\UserRepositoryInterface;
use DateTimeImmutable;

final class StartSubmission
{
    public function __construct(
        private readonly SubmissionRepositoryInterface $submissions,
        private readonly QuestionnaireRepositoryInterface $questionnaires,
        private readonly UserRepositoryInterface $users,
    ) {
    }

    public function execute(string $userId, string $questionnaireId): QuestionnaireSubmission
    {
        $existing = $this->submissions->findByUserAndQuestionnaire($userId, $questionnaireId);

        if ($existing !== null) {
            return $existing;
        }

        $submission = QuestionnaireSubmission::start(
            bin2hex(random_bytes(16)),
            $this->questionnaires->get($questionnaireId),
            $this->users->get($userId),
            new DateTimeImmutable(),
        );
        $this->submissions->save($submission);

        return $submission;
    }
}
