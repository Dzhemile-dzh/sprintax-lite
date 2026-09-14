<?php

declare(strict_types=1);

namespace App\Application\Submission\Review;

use App\Domain\Questionnaire\Entity\Question;
use App\Domain\Questionnaire\Entity\QuestionnaireStep;
use App\Domain\Submission\Entity\QuestionnaireSubmission;
use App\Domain\Submission\ValueObject\AnswerValue;

final readonly class ReviewView
{
    /**
     * @param list<array{step: QuestionnaireStep, questions: list<array{question: Question, answer: ?AnswerValue}>}> $steps
     */
    public function __construct(
        public QuestionnaireSubmission $submission,
        public array $steps,
        public bool $canFinalize,
        public ?string $resumeStepId,
    ) {
    }
}
