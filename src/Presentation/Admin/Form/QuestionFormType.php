<?php

declare(strict_types=1);

namespace App\Presentation\Admin\Form;

use App\Domain\Questionnaire\ValueObject\QuestionType;
use App\Domain\Questionnaire\ValueObject\VisibilityOperator;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\PositiveOrZero;

/**
 * @extends AbstractType<array{
 *     key: string,
 *     label: string,
 *     type: QuestionType,
 *     helpText: ?string,
 *     required: bool,
 *     min: ?int,
 *     max: ?int,
 *     regex: ?string,
 *     visibilityQuestionKey: ?string,
 *     visibilityOperator: ?VisibilityOperator,
 *     visibilityExpectedValue: ?string
 * }>
 */
final class QuestionFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('key', TextType::class, [
                'label' => 'Key',
                'constraints' => [
                    new NotBlank(),
                    new Length(max: 100),
                ],
            ])
            ->add('label', TextType::class, [
                'label' => 'Label',
                'constraints' => [
                    new NotBlank(),
                    new Length(max: 255),
                ],
            ])
            ->add('type', EnumType::class, [
                'class' => QuestionType::class,
                'label' => 'Type',
                'choice_label' => static fn (QuestionType $type): string => $type->value,
            ])
            ->add('helpText', TextType::class, [
                'label' => 'Help text',
                'required' => false,
            ])
            ->add('required', CheckboxType::class, [
                'label' => 'Required',
                'required' => false,
            ])
            ->add('min', IntegerType::class, [
                'label' => 'Min',
                'required' => false,
                'constraints' => [
                    new PositiveOrZero(),
                ],
            ])
            ->add('max', IntegerType::class, [
                'label' => 'Max',
                'required' => false,
                'constraints' => [
                    new PositiveOrZero(),
                ],
            ])
            ->add('regex', TextType::class, [
                'label' => 'Regex',
                'required' => false,
            ])
            ->add('visibilityQuestionKey', TextType::class, [
                'label' => 'Visible when question key',
                'required' => false,
            ])
            ->add('visibilityOperator', EnumType::class, [
                'class' => VisibilityOperator::class,
                'label' => 'Operator',
                'required' => false,
                'placeholder' => 'Always visible',
                'choice_label' => static fn (VisibilityOperator $operator): string => $operator->value,
            ])
            ->add('visibilityExpectedValue', TextType::class, [
                'label' => 'Expected value',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'question';
    }
}
