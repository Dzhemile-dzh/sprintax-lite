<?php

declare(strict_types=1);

namespace App\Domain\Audit;

use App\Domain\Audit\DTO\ConditionSnapshot;
use App\Domain\Audit\DTO\MappingSnapshot;
use App\Domain\Audit\DTO\OptionSnapshot;
use App\Domain\Audit\DTO\QuestionSnapshot;
use App\Domain\Audit\DTO\StepSnapshot;
use App\Domain\Audit\DTO\StructureSnapshot;
use App\Domain\Questionnaire\Entity\Question;
use App\Domain\Questionnaire\Entity\Questionnaire;
use App\Domain\Questionnaire\Entity\QuestionnaireStep;

final class QuestionnaireStructureSnapshot
{
    public function of(Questionnaire $questionnaire): StructureSnapshot
    {
        $steps = [];

        foreach ($questionnaire->steps() as $step) {
            $steps[] = $this->step($step);
        }

        $mappings = [];

        foreach ($questionnaire->mappings() as $mapping) {
            $coordinates = $mapping->coordinates();
            $mappings[] = new MappingSnapshot(
                $mapping->source()->type->value,
                $mapping->source()->reference,
                $coordinates->page,
                $coordinates->xMm,
                $coordinates->yMm,
                $coordinates->fontSize,
            );
        }

        return new StructureSnapshot(
            $questionnaire->name(),
            $questionnaire->formType()->value,
            $questionnaire->description(),
            $steps,
            $mappings,
        );
    }

    private function step(QuestionnaireStep $step): StepSnapshot
    {
        $questions = [];

        foreach ($step->questions() as $question) {
            $questions[] = $this->question($question);
        }

        return new StepSnapshot($step->title(), $step->position(), $questions);
    }

    private function question(Question $question): QuestionSnapshot
    {
        $options = [];

        foreach ($question->options() as $option) {
            $options[] = new OptionSnapshot($option->label(), $option->value(), $option->position());
        }

        $conditions = [];

        foreach ($question->visibility()->conditions as $condition) {
            $conditions[] = new ConditionSnapshot(
                $condition->questionKey,
                $condition->operator->value,
                $condition->expectedValue,
            );
        }

        $validation = $question->validation();

        return new QuestionSnapshot(
            $question->key(),
            $question->label(),
            $question->type()->value,
            $question->position(),
            $question->helpText(),
            $validation->required,
            $validation->min,
            $validation->max,
            $validation->regex,
            $options,
            $conditions,
        );
    }
}
