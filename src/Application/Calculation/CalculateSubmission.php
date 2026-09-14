<?php

declare(strict_types=1);

namespace App\Application\Calculation;

use App\Domain\Calculation\Contract\CalculatorInterface;
use App\Domain\Calculation\DTO\CalculationInput;
use App\Domain\Calculation\DTO\CalculationResult;
use App\Domain\Calculation\Exception\UnsupportedFormType;
use App\Domain\Submission\Repository\SubmissionRepositoryInterface;

final class CalculateSubmission
{
    /**
     * @param iterable<CalculatorInterface> $calculators
     */
    public function __construct(
        private readonly SubmissionRepositoryInterface $submissions,
        private readonly iterable $calculators,
    ) {
    }

    public function execute(string $submissionId): CalculationResult
    {
        $submission = $this->submissions->get($submissionId);
        $answers = [];

        foreach ($submission->answers() as $answer) {
            $answers[$answer->question()->key()] = $answer->value()->raw();
        }

        $input = new CalculationInput($submission->questionnaire()->formType(), $answers);

        foreach ($this->calculators as $calculator) {
            if ($calculator->supports($input->formType)) {
                return $calculator->calculate($input);
            }
        }

        throw UnsupportedFormType::for($input->formType);
    }
}
