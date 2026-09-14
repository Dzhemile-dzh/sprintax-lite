<?php

declare(strict_types=1);

namespace App\Application\Questionnaire\Update;

use App\Domain\Questionnaire\Repository\QuestionnaireRepositoryInterface;

final class UpdateQuestionnaire
{
    public function __construct(
        private readonly QuestionnaireRepositoryInterface $questionnaires,
    ) {
    }

    public function execute(string $id, string $name, ?string $description): void
    {
        $questionnaire = $this->questionnaires->get($id);
        $questionnaire->rename($name);
        $questionnaire->changeDescription($description);
        $this->questionnaires->save($questionnaire);
    }
}
