<?php

declare(strict_types=1);

namespace App\Domain\Questionnaire\Repository;

use App\Domain\Questionnaire\Entity\Questionnaire;

interface QuestionnaireRepositoryInterface
{
    public function get(string $id): Questionnaire;

    public function save(Questionnaire $questionnaire): void;
}
