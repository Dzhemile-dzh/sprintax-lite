<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Domain\Questionnaire\Entity\Questionnaire;
use App\Domain\Questionnaire\Exception\QuestionnaireNotFound;
use App\Domain\Questionnaire\Repository\QuestionnaireRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineQuestionnaireRepository implements QuestionnaireRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function get(string $id): Questionnaire
    {
        /** @var list<Questionnaire> $questionnaires */
        $questionnaires = $this->entityManager->createQueryBuilder()
            ->select('questionnaire', 'step', 'question', 'questionOption', 'mapping')
            ->from(Questionnaire::class, 'questionnaire')
            ->leftJoin('questionnaire.steps', 'step')
            ->leftJoin('step.questions', 'question')
            ->leftJoin('question.options', 'questionOption')
            ->leftJoin('questionnaire.mappings', 'mapping')
            ->where('questionnaire.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getResult();

        $questionnaire = $questionnaires[0] ?? null;

        if (!$questionnaire instanceof Questionnaire) {
            throw QuestionnaireNotFound::withId($id);
        }

        return $questionnaire;
    }

    public function save(Questionnaire $questionnaire): void
    {
        $this->entityManager->persist($questionnaire);
        $this->entityManager->flush();
    }
}
