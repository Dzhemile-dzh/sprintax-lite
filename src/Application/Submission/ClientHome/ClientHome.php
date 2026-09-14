<?php

declare(strict_types=1);

namespace App\Application\Submission\ClientHome;

use App\Domain\Questionnaire\Entity\Questionnaire;
use App\Domain\Submission\Entity\QuestionnaireSubmission;

final readonly class ClientHome
{
    /**
     * @param list<Questionnaire> $questionnaires
     * @param array<string, QuestionnaireSubmission> $submissionsByQuestionnaireId
     */
    public function __construct(
        public array $questionnaires,
        public array $submissionsByQuestionnaireId,
    ) {
    }
}
