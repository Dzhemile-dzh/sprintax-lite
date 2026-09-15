<?php

declare(strict_types=1);

namespace App\Presentation\Client\Form;

use App\Domain\Questionnaire\Entity\Question;
use App\Domain\Questionnaire\ValueObject\QuestionType;
use App\Domain\Submission\ValueObject\AnswerValue;
use App\Domain\Submission\ValueObject\StoredDate;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<array<string, mixed>>
 */
final class WizardStepFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var list<Question> $questions */
        $questions = $options['questions'];

        foreach ($questions as $question) {
            $builder->add($question->key(), $this->fieldType($question), $this->fieldOptions($question));
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('questions');
        $resolver->setAllowedTypes('questions', 'array');
        $resolver->setDefaults([
            'allow_extra_fields' => true,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'wizard_step';
    }

    /**
     * @param list<Question> $questions
     * @param array<string, AnswerValue> $answersByKey
     *
     * @return array<string, mixed>
     */
    public static function dataFromAnswers(array $questions, array $answersByKey): array
    {
        $data = [];

        foreach ($questions as $question) {
            $answer = $answersByKey[$question->key()] ?? null;

            if ($answer === null) {
                continue;
            }

            $raw = $answer->raw();

            if ($question->type() === QuestionType::Date) {
                if (!is_string($raw) || trim($raw) === '') {
                    continue;
                }

                $date = StoredDate::tryFrom($raw);

                if ($date instanceof StoredDate) {
                    $data[$question->key()] = $date->toDateTime();
                }

                continue;
            }

            $data[$question->key()] = $raw;
        }

        return $data;
    }

    /**
     * @return class-string<TextType|NumberType|DateType|ChoiceType>
     */
    private function fieldType(Question $question): string
    {
        return match ($question->type()) {
            QuestionType::ShortText => TextType::class,
            QuestionType::Number => NumberType::class,
            QuestionType::Date => DateType::class,
            QuestionType::SingleChoice, QuestionType::MultiChoice, QuestionType::YesNo => ChoiceType::class,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function fieldOptions(Question $question): array
    {
        $required = $question->validation()->required;
        $options = [
            'label' => $question->label(),
            'required' => $required,
        ];

        if ($question->helpText() !== null) {
            $options['help'] = $question->helpText();
        }

        return match ($question->type()) {
            QuestionType::ShortText => $options,
            QuestionType::Number => [
                ...$options,
                'html5' => true,
            ],
            QuestionType::Date => [
                ...$options,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ],
            QuestionType::YesNo => [
                ...$options,
                'choices' => [
                    'Yes' => 'yes',
                    'No' => 'no',
                ],
                'expanded' => true,
                'multiple' => false,
                'placeholder' => $required ? false : 'Choose',
            ],
            QuestionType::SingleChoice => [
                ...$options,
                'choices' => $this->choiceChoices($question),
                'expanded' => false,
                'multiple' => false,
                'placeholder' => $required ? false : 'Choose',
            ],
            QuestionType::MultiChoice => [
                ...$options,
                'choices' => $this->choiceChoices($question),
                'expanded' => true,
                'multiple' => true,
            ],
        };
    }

    /**
     * @return array<string, string>
     */
    private function choiceChoices(Question $question): array
    {
        $choices = [];

        foreach ($question->options() as $option) {
            $choices[$option->label()] = $option->value();
        }

        return $choices;
    }
}
