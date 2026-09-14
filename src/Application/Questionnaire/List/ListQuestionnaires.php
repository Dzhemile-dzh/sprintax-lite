<?php

declare(strict_types=1);

namespace App\Application\Questionnaire\List;

use App\Domain\Questionnaire\Entity\Questionnaire;
use App\Domain\Questionnaire\Repository\QuestionnaireRepositoryInterface;

final class ListQuestionnaires
{
    public function __construct(
        private readonly QuestionnaireRepositoryInterface $questionnaires,
    ) {
    }

    /**
     * @return list<Questionnaire>
     */
    public function execute(): array
    {
        return $this->questionnaires->all();
    }
}
