<?php

declare(strict_types=1);

namespace App\Presentation\Admin\Form;

use App\Domain\Questionnaire\ValueObject\MappingSourceType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;

/**
 * @extends AbstractType<array{
 *     sourceType: MappingSourceType,
 *     questionKey: ?string,
 *     computedField: ?string,
 *     page: int,
 *     xMm: float,
 *     yMm: float,
 *     fontSize: ?int
 * }>
 */
final class MappingFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $lockSource = $options['lock_source'] === true;

        $builder
            ->add('sourceType', EnumType::class, [
                'class' => MappingSourceType::class,
                'label' => 'Source',
                'disabled' => $lockSource,
                'choice_label' => static fn (MappingSourceType $type): string => $type->value,
            ])
            ->add('questionKey', ChoiceType::class, [
                'label' => 'Question',
                'required' => false,
                'placeholder' => 'Choose a question',
                'disabled' => $lockSource,
                'choices' => $options['question_keys'],
            ])
            ->add('computedField', ChoiceType::class, [
                'label' => 'Computed field',
                'required' => false,
                'placeholder' => 'Choose a computed field',
                'disabled' => $lockSource,
                'choices' => $options['computed_fields'],
            ])
            ->add('page', IntegerType::class, [
                'label' => 'Page',
                'constraints' => [
                    new NotBlank(),
                    new Positive(),
                ],
            ])
            ->add('xMm', NumberType::class, [
                'label' => 'X (mm)',
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->add('yMm', NumberType::class, [
                'label' => 'Y (mm)',
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->add('fontSize', IntegerType::class, [
                'label' => 'Font size',
                'required' => false,
                'constraints' => [
                    new Positive(),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'lock_source' => false,
            'question_keys' => [],
            'computed_fields' => [],
        ]);
        $resolver->setAllowedTypes('lock_source', 'bool');
        $resolver->setAllowedTypes('question_keys', 'array');
        $resolver->setAllowedTypes('computed_fields', 'array');
    }

    public function getBlockPrefix(): string
    {
        return 'mapping';
    }
}
