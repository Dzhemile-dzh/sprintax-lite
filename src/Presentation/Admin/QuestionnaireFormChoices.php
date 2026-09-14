<?php

declare(strict_types=1);

namespace App\Presentation\Admin;

use App\Domain\Questionnaire\Entity\Questionnaire;

final class QuestionnaireFormChoices
{
    /**
     * @return array<string, string>
     */
    public static function questionKeys(Questionnaire $questionnaire, ?string $excludeKey = null): array
    {
        $choices = [];

        foreach ($questionnaire->allQuestions() as $question) {
            if ($excludeKey !== null && $question->key() === $excludeKey) {
                continue;
            }

            $choices[sprintf('%s (%s)', $question->label(), $question->key())] = $question->key();
        }

        return $choices;
    }

    /**
     * @param list<string> $fields
     *
     * @return array<string, string>
     */
    public static function computedFields(array $fields): array
    {
        $choices = [];

        foreach ($fields as $field) {
            $choices[$field] = $field;
        }

        return $choices;
    }
}
