<?php

declare(strict_types=1);

namespace App\Application\Pdf;

use App\Application\Calculation\CalculateSubmission;
use App\Domain\Calculation\DTO\CalculationResult;
use App\Domain\Filesystem\Contract\FileStorageInterface;
use App\Domain\Pdf\Contract\PdfGeneratorInterface;
use App\Domain\Pdf\DTO\PdfFieldPlacement;
use App\Domain\Pdf\DTO\PdfGenerationRequest;
use App\Domain\Pdf\Exception\PdfGenerationFailed;
use App\Domain\Questionnaire\Entity\QuestionMapping;
use App\Domain\Questionnaire\Entity\Questionnaire;
use App\Domain\Questionnaire\QuestionVisibilityEvaluator;
use App\Domain\Questionnaire\ValueObject\FormType;
use App\Domain\Submission\Entity\QuestionnaireSubmission;
use App\Domain\Submission\Exception\InvalidSubmission;
use App\Domain\Submission\Repository\SubmissionRepositoryInterface;
use App\Domain\Submission\ValueObject\AnswerValue;
use App\Domain\Submission\ValueObject\SubmissionStatus;
use DateTimeImmutable;

final class GenerateSubmissionPdf
{
    public function __construct(
        private readonly SubmissionRepositoryInterface $submissions,
        private readonly CalculateSubmission $calculateSubmission,
        private readonly PdfGeneratorInterface $pdfGenerator,
        private readonly QuestionVisibilityEvaluator $visibility,
        private readonly FileStorageInterface $fileStorage,
        private readonly string $templatesDirectory,
        private readonly string $outputDirectory,
    ) {
    }

    public function execute(string $submissionId): string
    {
        $submission = $this->submissions->get($submissionId);

        if ($submission->status() === SubmissionStatus::InProgress) {
            throw InvalidSubmission::cannotGeneratePdf($submission->status());
        }

        $outputPath = $this->outputPathFor($submission->id());
        $existingPath = $this->existingPdfPath($submission, $outputPath);

        if ($existingPath !== null) {
            return $existingPath;
        }

        $questionnaire = $submission->questionnaire();
        $answersByKey = $this->answersByQuestionKey($submission);
        $calculation = $this->calculationIfNeeded($submission, $questionnaire);

        $this->pdfGenerator->generate(new PdfGenerationRequest(
            $this->templatePath($questionnaire->formType()),
            $outputPath,
            $this->overlayFields($submission, $questionnaire, $answersByKey, $calculation),
        ));

        $submission->markPdfReady(new DateTimeImmutable(), $this->storedPdfPath($submission->id()));
        $this->submissions->save($submission);

        return $outputPath;
    }

    private function existingPdfPath(QuestionnaireSubmission $submission, string $outputPath): ?string
    {
        if ($submission->status() !== SubmissionStatus::PdfReady) {
            return null;
        }

        if ($this->fileStorage->exists($outputPath)) {
            return $outputPath;
        }

        return null;
    }

    private function storedPdfPath(string $submissionId): string
    {
        return $submissionId.'.pdf';
    }

    private function outputPathFor(string $submissionId): string
    {
        return $this->outputDirectory.DIRECTORY_SEPARATOR.$this->storedPdfPath($submissionId);
    }

    /**
     * @param array<string, AnswerValue> $answersByKey
     *
     * @return list<array{placement: PdfFieldPlacement, value: string}>
     */
    private function overlayFields(
        QuestionnaireSubmission $submission,
        Questionnaire $questionnaire,
        array $answersByKey,
        ?CalculationResult $calculation,
    ): array {
        $fields = [];

        foreach ($questionnaire->mappings() as $mapping) {
            $value = $this->valueForMapping($mapping, $submission, $questionnaire, $answersByKey, $calculation);

            if ($value === null || trim($value) === '') {
                continue;
            }

            $coordinates = $mapping->coordinates();
            $fields[] = [
                'placement' => new PdfFieldPlacement(
                    $coordinates->page,
                    $coordinates->xMm,
                    $coordinates->yMm,
                    $coordinates->fontSize,
                ),
                'value' => $value,
            ];
        }

        return $fields;
    }

    /**
     * @param array<string, AnswerValue> $answersByKey
     */
    private function valueForMapping(
        QuestionMapping $mapping,
        QuestionnaireSubmission $submission,
        Questionnaire $questionnaire,
        array $answersByKey,
        ?CalculationResult $calculation,
    ): ?string {
        $source = $mapping->source();

        if (!$source->isQuestion()) {
            if ($calculation === null || !$calculation->has($source->reference)) {
                return null;
            }

            return $this->formatComputed($calculation->value($source->reference));
        }

        $question = $questionnaire->findQuestion($source->reference);

        if ($question === null) {
            throw PdfGenerationFailed::unknownQuestion($source->reference);
        }

        if (!$this->visibility->isVisible($question, $questionnaire, $answersByKey)) {
            return null;
        }

        $answer = $submission->answerFor($question->id());

        if ($answer === null) {
            return null;
        }

        return $this->formatAnswer($answer->value());
    }

    private function formatAnswer(AnswerValue $value): ?string
    {
        if (!$value->isProvided()) {
            return null;
        }

        $raw = $value->raw();

        if (is_array($raw)) {
            if ($raw === []) {
                return null;
            }

            return implode(', ', $raw);
        }

        return $raw;
    }

    private function formatComputed(int|float|string $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value)) {
            return (string) $value;
        }

        return number_format($value, 2, '.', '');
    }

    private function calculationIfNeeded(
        QuestionnaireSubmission $submission,
        Questionnaire $questionnaire,
    ): ?CalculationResult {
        foreach ($questionnaire->mappings() as $mapping) {
            if (!$mapping->source()->isQuestion()) {
                return $this->calculateSubmission->execute($submission->id());
            }
        }

        return null;
    }

    /**
     * @return array<string, AnswerValue>
     */
    private function answersByQuestionKey(QuestionnaireSubmission $submission): array
    {
        $answersByKey = [];

        foreach ($submission->answers() as $answer) {
            $answersByKey[$answer->question()->key()] = $answer->value();
        }

        return $answersByKey;
    }

    private function templatePath(FormType $formType): string
    {
        $path = $this->templatesDirectory.DIRECTORY_SEPARATOR.$formType->value.'.pdf';

        if (!$this->fileStorage->exists($path)) {
            throw PdfGenerationFailed::templateMissing($formType->value, $path);
        }

        return $path;
    }
}
