<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Domain\Audit\Entity\QuestionnaireRevision;
use App\Domain\Audit\Repository\QuestionnaireRevisionRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineQuestionnaireRevisionRepository implements QuestionnaireRevisionRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function add(QuestionnaireRevision $revision): void
    {
        $this->entityManager->persist($revision);
        $this->entityManager->flush();
    }

    public function nextVersion(string $questionnaireId): int
    {
        $highest = $this->entityManager->createQueryBuilder()
            ->select('MAX(revision.version)')
            ->from(QuestionnaireRevision::class, 'revision')
            ->where('revision.questionnaireId = :questionnaireId')
            ->setParameter('questionnaireId', $questionnaireId)
            ->getQuery()
            ->getSingleScalarResult();

        if (!is_int($highest) && !is_string($highest)) {
            return 1;
        }

        return (int) $highest + 1;
    }

    /**
     * @return list<QuestionnaireRevision>
     */
    public function forQuestionnaire(string $questionnaireId): array
    {
        /** @var list<QuestionnaireRevision> $revisions */
        $revisions = $this->entityManager->createQueryBuilder()
            ->select('revision')
            ->from(QuestionnaireRevision::class, 'revision')
            ->where('revision.questionnaireId = :questionnaireId')
            ->setParameter('questionnaireId', $questionnaireId)
            ->orderBy('revision.version', 'DESC')
            ->getQuery()
            ->getResult();

        return $revisions;
    }

    public function find(string $questionnaireId, int $version): ?QuestionnaireRevision
    {
        $revision = $this->entityManager->createQueryBuilder()
            ->select('revision')
            ->from(QuestionnaireRevision::class, 'revision')
            ->where('revision.questionnaireId = :questionnaireId')
            ->andWhere('revision.version = :version')
            ->setParameter('questionnaireId', $questionnaireId)
            ->setParameter('version', $version)
            ->getQuery()
            ->getOneOrNullResult();

        return $revision instanceof QuestionnaireRevision ? $revision : null;
    }

    /**
     * @return list<QuestionnaireRevision>
     */
    public function latest(int $limit): array
    {
        /** @var list<QuestionnaireRevision> $revisions */
        $revisions = $this->entityManager->createQueryBuilder()
            ->select('revision')
            ->from(QuestionnaireRevision::class, 'revision')
            ->orderBy('revision.recordedAt', 'DESC')
            ->addOrderBy('revision.version', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $revisions;
    }
}
