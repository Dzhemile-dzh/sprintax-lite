<?php

declare(strict_types=1);

namespace App\Presentation\Api;

use App\Domain\Questionnaire\Entity\Question;
use App\Domain\Questionnaire\Entity\QuestionMapping;
use App\Domain\Questionnaire\Entity\Questionnaire;
use App\Domain\Questionnaire\Entity\QuestionnaireStep;
use App\Domain\Questionnaire\Entity\QuestionOption;
use App\Domain\Submission\Entity\QuestionnaireSubmission;
use App\Domain\Submission\ValueObject\AnswerValue;
use App\Domain\Submission\ValueObject\SubmissionStatus;

/**
 * Maps domain objects to plain JSON-safe arrays for the read API.
 */
final class ApiSerializer
{
    /**
     * @return array<string, mixed>
     */
    public function questionnaireSummary(Questionnaire $questionnaire): array
    {
        return [
            'id' => $questionnaire->id(),
            'name' => $questionnaire->name(),
            'formType' => $questionnaire->formType()->value,
            'description' => $questionnaire->description(),
            'stepCount' => count($questionnaire->steps()),
            'questionCount' => count($questionnaire->allQuestions()),
            'mappingCount' => count($questionnaire->mappings()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function questionnaireDetail(Questionnaire $questionnaire): array
    {
        return [
            ...$this->questionnaireSummary($questionnaire),
            'steps' => array_map($this->step(...), $questionnaire->steps()),
            'mappings' => array_map($this->mapping(...), $questionnaire->mappings()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function submission(QuestionnaireSubmission $submission): array
    {
        $answers = [];

        foreach ($submission->answers() as $answer) {
            $answers[] = [
                'questionKey' => $answer->question()->key(),
                'value' => $this->answerValue($answer->value()),
            ];
        }

        return [
            'id' => $submission->id(),
            'questionnaireId' => $submission->questionnaire()->id(),
            'questionnaireName' => $submission->questionnaire()->name(),
            'status' => $submission->status()->value,
            'currentStepId' => $submission->currentStep()->id(),
            'createdAt' => $submission->createdAt()->format(DATE_ATOM),
            'updatedAt' => $submission->updatedAt()->format(DATE_ATOM),
            'finalizedAt' => $submission->finalizedAt()?->format(DATE_ATOM),
            'pdfReady' => $submission->status() === SubmissionStatus::PdfReady,
            'pdfEmailedAt' => $submission->pdfEmailedAt()?->format(DATE_ATOM),
            'answers' => $answers,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function step(QuestionnaireStep $step): array
    {
        return [
            'id' => $step->id(),
            'title' => $step->title(),
            'position' => $step->position(),
            'questions' => array_map($this->question(...), $step->questions()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function question(Question $question): array
    {
        $validation = $question->validation();
        $conditions = [];

        foreach ($question->visibility()->conditions as $condition) {
            $conditions[] = [
                'questionKey' => $condition->questionKey,
                'operator' => $condition->operator->value,
                'expectedValue' => $condition->expectedValue,
            ];
        }

        return [
            'id' => $question->id(),
            'key' => $question->key(),
            'label' => $question->label(),
            'type' => $question->type()->value,
            'position' => $question->position(),
            'helpText' => $question->helpText(),
            'validation' => [
                'required' => $validation->required,
                'min' => $validation->min,
                'max' => $validation->max,
                'regex' => $validation->regex,
            ],
            'visibility' => $conditions,
            'options' => array_map($this->option(...), $question->options()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function option(QuestionOption $option): array
    {
        return [
            'id' => $option->id(),
            'label' => $option->label(),
            'value' => $option->value(),
            'position' => $option->position(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapping(QuestionMapping $mapping): array
    {
        $coordinates = $mapping->coordinates();

        return [
            'id' => $mapping->id(),
            'sourceType' => $mapping->source()->type->value,
            'sourceReference' => $mapping->source()->reference,
            'page' => $coordinates->page,
            'xMm' => $coordinates->xMm,
            'yMm' => $coordinates->yMm,
            'fontSize' => $coordinates->fontSize,
        ];
    }

    /**
     * @return string|list<string>
     */
    private function answerValue(AnswerValue $value): string|array
    {
        return $value->raw();
    }
}
