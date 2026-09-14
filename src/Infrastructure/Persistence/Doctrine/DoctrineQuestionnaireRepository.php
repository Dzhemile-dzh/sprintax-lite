<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Domain\Questionnaire\Entity\Questionnaire;
use App\Domain\Questionnaire\Exception\QuestionnaireNotFound;
use App\Domain\Questionnaire\Repository\QuestionnaireRepositoryInterface;
use LogicException;

final class DoctrineQuestionnaireRepository implements QuestionnaireRepositoryInterface
{
    public function get(string $id): Questionnaire
    {
        throw QuestionnaireNotFound::withId($id);
    }

    public function save(Questionnaire $questionnaire): void
    {
        throw new LogicException(sprintf(
            'Doctrine persistence is not implemented yet for %s.',
            $questionnaire::class,
        ));
    }
}
