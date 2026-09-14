<?php

declare(strict_types=1);

namespace App\Presentation\Admin\Form;

use App\Domain\Questionnaire\ValueObject\MappingSourceType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;

/**
 * @extends AbstractType<array{
 *     sourceType: MappingSourceType,
 *     sourceReference: string,
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
        $builder
            ->add('sourceType', EnumType::class, [
                'class' => MappingSourceType::class,
                'label' => 'Source',
                'choice_label' => static fn (MappingSourceType $type): string => $type->value,
            ])
            ->add('sourceReference', TextType::class, [
                'label' => 'Question id or computed field',
                'constraints' => [
                    new NotBlank(),
                    new Length(max: 100),
                ],
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
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'mapping';
    }
}
