<?php

declare(strict_types=1);

namespace App\Domain\Audit\ValueObject;

enum RevisionAction: string
{
    case QuestionnaireCreated = 'questionnaire_created';
    case QuestionnaireUpdated = 'questionnaire_updated';
    case StepAdded = 'step_added';
    case StepRenamed = 'step_renamed';
    case StepRemoved = 'step_removed';
    case QuestionAdded = 'question_added';
    case QuestionUpdated = 'question_updated';
    case QuestionRemoved = 'question_removed';
    case OptionAdded = 'option_added';
    case OptionUpdated = 'option_updated';
    case OptionRemoved = 'option_removed';
    case MappingAdded = 'mapping_added';
    case MappingRelocated = 'mapping_relocated';
    case MappingRemoved = 'mapping_removed';

    public function label(): string
    {
        return match ($this) {
            self::QuestionnaireCreated => 'Questionnaire created',
            self::QuestionnaireUpdated => 'Questionnaire updated',
            self::StepAdded => 'Step added',
            self::StepRenamed => 'Step renamed',
            self::StepRemoved => 'Step removed',
            self::QuestionAdded => 'Question added',
            self::QuestionUpdated => 'Question updated',
            self::QuestionRemoved => 'Question removed',
            self::OptionAdded => 'Option added',
            self::OptionUpdated => 'Option updated',
            self::OptionRemoved => 'Option removed',
            self::MappingAdded => 'PDF mapping added',
            self::MappingRelocated => 'PDF mapping moved',
            self::MappingRemoved => 'PDF mapping removed',
        };
    }
}
