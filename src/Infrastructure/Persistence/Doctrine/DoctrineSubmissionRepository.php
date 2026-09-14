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
            ->select(
                'submission',
                'answer',
                'answeredQuestion',
                'questionnaire',
                'owner',
                'currentStep',
                'step',
                'question',
                'questionOption',
            )
            ->from(QuestionnaireSubmission::class, 'submission')
            ->leftJoin('submission.answers', 'answer')
            ->leftJoin('answer.question', 'answeredQuestion')
            ->leftJoin('submission.questionnaire', 'questionnaire')
            ->leftJoin('submission.user', 'owner')
            ->leftJoin('submission.currentStep', 'currentStep')
            ->leftJoin('questionnaire.steps', 'step')
            ->leftJoin('step.questions', 'question')
            ->leftJoin('question.options', 'questionOption')
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

    public function findByUserAndQuestionnaire(string $userId, string $questionnaireId): ?QuestionnaireSubmission
    {
        $submission = $this->entityManager->createQueryBuilder()
            ->select('submission')
            ->from(QuestionnaireSubmission::class, 'submission')
            ->where('IDENTITY(submission.user) = :userId')
            ->andWhere('IDENTITY(submission.questionnaire) = :questionnaireId')
            ->setParameter('userId', $userId)
            ->setParameter('questionnaireId', $questionnaireId)
            ->getQuery()
            ->getOneOrNullResult();

        return $submission instanceof QuestionnaireSubmission ? $submission : null;
    }

    /**
     * @return list<QuestionnaireSubmission>
     */
    public function findForUser(string $userId): array
    {
        /** @var list<QuestionnaireSubmission> $submissions */
        $submissions = $this->entityManager->createQueryBuilder()
            ->select('submission', 'questionnaire', 'currentStep')
            ->from(QuestionnaireSubmission::class, 'submission')
            ->join('submission.questionnaire', 'questionnaire')
            ->join('submission.currentStep', 'currentStep')
            ->where('IDENTITY(submission.user) = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getResult();

        return $submissions;
    }

    public function existsForQuestionnaire(string $questionnaireId): bool
    {
        $count = $this->entityManager->createQueryBuilder()
            ->select('COUNT(submission.id)')
            ->from(QuestionnaireSubmission::class, 'submission')
            ->where('IDENTITY(submission.questionnaire) = :questionnaireId')
            ->setParameter('questionnaireId', $questionnaireId)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count > 0;
    }

    public function save(QuestionnaireSubmission $submission): void
    {
        $this->entityManager->persist($submission);
        $this->entityManager->flush();
    }
}
