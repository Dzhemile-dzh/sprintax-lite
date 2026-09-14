<?php

declare(strict_types=1);

namespace App\Application\Questionnaire\Create;

use App\Domain\Questionnaire\Entity\Questionnaire;
use App\Domain\Questionnaire\Repository\QuestionnaireRepositoryInterface;

final class CreateQuestionnaire
{
    public function __construct(
        private readonly QuestionnaireRepositoryInterface $questionnaires,
    ) {
    }

    public function execute(string $name, ?string $description): Questionnaire
    {
        $questionnaire = Questionnaire::create(
            bin2hex(random_bytes(16)),
            $name,
            $description,
        );
        $this->questionnaires->save($questionnaire);

        return $questionnaire;
    }
}
