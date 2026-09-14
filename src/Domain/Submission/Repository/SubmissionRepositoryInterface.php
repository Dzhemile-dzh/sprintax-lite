<?php

declare(strict_types=1);

namespace App\Domain\Submission\Repository;

use App\Domain\Submission\Entity\QuestionnaireSubmission;

interface SubmissionRepositoryInterface
{
    public function get(string $id): QuestionnaireSubmission;

    public function save(QuestionnaireSubmission $submission): void;
}
