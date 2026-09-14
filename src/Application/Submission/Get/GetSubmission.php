<?php

declare(strict_types=1);

namespace App\Application\Submission\Get;

use App\Domain\Submission\Entity\QuestionnaireSubmission;
use App\Domain\Submission\Repository\SubmissionRepositoryInterface;

final class GetSubmission
{
    public function __construct(
        private readonly SubmissionRepositoryInterface $submissions,
    ) {
    }

    public function execute(string $id): QuestionnaireSubmission
    {
        return $this->submissions->get($id);
    }
}
