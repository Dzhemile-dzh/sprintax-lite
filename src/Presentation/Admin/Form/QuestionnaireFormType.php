<?php

declare(strict_types=1);

namespace App\Presentation\Admin\Form;

use App\Domain\Questionnaire\ValueObject\FormType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @extends AbstractType<array{name: string, formType: FormType, description: ?string}>
 */
final class QuestionnaireFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $locked = $options['form_type_locked'] === true;

        $builder
            ->add('name', TextType::class, [
                'label' => 'Name',
                'constraints' => [
                    new NotBlank(),
                    new Length(max: 255),
                ],
            ])
            ->add('formType', EnumType::class, [
                'class' => FormType::class,
                'label' => 'Form type',
                'help' => $locked
                    ? 'Form type cannot change after a client has started this questionnaire.'
                    : 'Selects the tax calculator and PDF template. The name is only a label.',
                'choice_label' => static fn (FormType $type): string => $type->label(),
                'choice_filter' => static fn (?FormType $type): bool => $type?->isImplemented() === true,
                'disabled' => $locked,
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'constraints' => [
                    new Length(max: 4000),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'form_type_locked' => false,
        ]);
        $resolver->setAllowedTypes('form_type_locked', 'bool');
    }

    public function getBlockPrefix(): string
    {
        return 'questionnaire';
    }
}
