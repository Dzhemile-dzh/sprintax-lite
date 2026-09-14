<?php

declare(strict_types=1);

namespace App\Domain\Questionnaire;

use App\Domain\Questionnaire\Entity\Question;
use App\Domain\Questionnaire\Entity\Questionnaire;
use App\Domain\Questionnaire\Entity\QuestionnaireStep;
use App\Domain\Questionnaire\ValueObject\VisibilityCondition;
use App\Domain\Questionnaire\ValueObject\VisibilityOperator;
use App\Domain\Submission\ValueObject\AnswerValue;

final class QuestionVisibilityEvaluator
{
    /**
     * @param array<string, AnswerValue> $answersByQuestionKey
     */
    public function isVisible(Question $question, Questionnaire $questionnaire, array $answersByQuestionKey): bool
    {
        return $this->evaluate($question, $questionnaire, $answersByQuestionKey, []);
    }

    /**
     * @param array<string, AnswerValue> $answersByQuestionKey
     * @return list<Question>
     */
    public function visibleQuestionsOnStep(
        QuestionnaireStep $step,
        Questionnaire $questionnaire,
        array $answersByQuestionKey,
    ): array {
        $visible = [];

        foreach ($step->questions() as $question) {
            if ($this->isVisible($question, $questionnaire, $answersByQuestionKey)) {
                $visible[] = $question;
            }
        }

        return $visible;
    }

    /**
     * @param array<string, AnswerValue> $answersByQuestionKey
     * @param array<string, true> $visitedQuestionKeys
     */
    private function evaluate(
        Question $question,
        Questionnaire $questionnaire,
        array $answersByQuestionKey,
        array $visitedQuestionKeys,
    ): bool {
        if ($questionnaire->findQuestion($question->id()) === null) {
            return false;
        }

        $rule = $question->visibility();

        if ($rule->isAlwaysVisible()) {
            return true;
        }

        if (isset($visitedQuestionKeys[$question->key()])) {
            return false;
        }

        $visitedQuestionKeys[$question->key()] = true;

        foreach ($rule->conditions as $condition) {
            if (!$this->conditionHolds($condition, $questionnaire, $answersByQuestionKey, $visitedQuestionKeys)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, AnswerValue> $answersByQuestionKey
     * @param array<string, true> $visitedQuestionKeys
     */
    private function conditionHolds(
        VisibilityCondition $condition,
        Questionnaire $questionnaire,
        array $answersByQuestionKey,
        array $visitedQuestionKeys,
    ): bool {
        $controller = $questionnaire->findQuestionByKey($condition->questionKey);

        if ($controller === null) {
            return false;
        }

        if (!$this->evaluate($controller, $questionnaire, $answersByQuestionKey, $visitedQuestionKeys)) {
            return false;
        }

        $answer = $answersByQuestionKey[$condition->questionKey] ?? null;

        if ($answer === null || !$answer->isProvided()) {
            return false;
        }

        $matches = $answer->includes($condition->expectedValue);

        return match ($condition->operator) {
            VisibilityOperator::Equals => $matches,
            VisibilityOperator::NotEquals => !$matches,
        };
    }
}
