<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Domain\Submission\Entity\QuestionnaireSubmission;
use App\Domain\Submission\Exception\SubmissionNotFound;
use App\Domain\Submission\Repository\SubmissionRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineSubmissionRepository implements SubmissionRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function get(string $id): QuestionnaireSubmission
    {
        /** @var list<QuestionnaireSubmission> $submissions */
        $submissions = $this->entityManager->createQueryBuilder()
            ->select('submission', 'answer', 'question', 'questionnaire', 'owner', 'currentStep')
            ->from(QuestionnaireSubmission::class, 'submission')
            ->leftJoin('submission.answers', 'answer')
            ->leftJoin('answer.question', 'question')
            ->leftJoin('submission.questionnaire', 'questionnaire')
            ->leftJoin('submission.user', 'owner')
            ->leftJoin('submission.currentStep', 'currentStep')
            ->where('submission.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getResult();

        $submission = $submissions[0] ?? null;

        if (!$submission instanceof QuestionnaireSubmission) {
            throw SubmissionNotFound::withId($id);
        }

        return $submission;
    }

    public function save(QuestionnaireSubmission $submission): void
    {
        $this->entityManager->persist($submission);
        $this->entityManager->flush();
    }
}
