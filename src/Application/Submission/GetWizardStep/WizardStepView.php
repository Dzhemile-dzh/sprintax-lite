<?php

declare(strict_types=1);

namespace App\Application\Submission\GetWizardStep;

use App\Domain\Questionnaire\Entity\Question;
use App\Domain\Questionnaire\Entity\QuestionnaireStep;
use App\Domain\Submission\Entity\QuestionnaireSubmission;
use LogicException;

final readonly class WizardStepView
{
    /**
     * @param list<Question> $visibleQuestions
     */
    private function __construct(
        public QuestionnaireSubmission $submission,
        public ?QuestionnaireStep $step,
        public array $visibleQuestions,
        public ?string $previousStepId,
        public bool $openForEditing,
    ) {
    }

    /**
     * @param list<Question> $visibleQuestions
     */
    public static function forEditing(
        QuestionnaireSubmission $submission,
        QuestionnaireStep $step,
        array $visibleQuestions,
        ?string $previousStepId,
    ): self {
        return new self($submission, $step, $visibleQuestions, $previousStepId, true);
    }

    public static function sendToReview(QuestionnaireSubmission $submission): self
    {
        return new self($submission, null, [], null, false);
    }

    public function editableStep(): QuestionnaireStep
    {
        if ($this->step === null) {
            throw new LogicException('This wizard step is not open for editing.');
        }

        return $this->step;
    }
}
