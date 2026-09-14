<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Domain\Submission\Entity\QuestionnaireSubmission;
use App\Domain\Submission\Exception\SubmissionNotFound;
use App\Domain\Submission\Repository\SubmissionRepositoryInterface;
use LogicException;

final class DoctrineSubmissionRepository implements SubmissionRepositoryInterface
{
    public function get(string $id): QuestionnaireSubmission
    {
        throw SubmissionNotFound::withId($id);
    }

    public function save(QuestionnaireSubmission $submission): void
    {
        throw new LogicException(sprintf(
            'Doctrine persistence is not implemented yet for %s.',
            $submission::class,
        ));
    }
}
