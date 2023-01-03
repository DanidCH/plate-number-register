<?php

namespace App\Form;

use App\Entity\NumberPlate;
use Karser\Recaptcha3Bundle\Form\Recaptcha3Type;
use Karser\Recaptcha3Bundle\Validator\Constraints\Recaptcha3;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotNull;

class NumberPlateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('numberPlate')
            ->add('file', FileType::class, [
                'required' => true,
                'constraints' => [
                    new NotNull(),
                ],
            ])
            ->add('submit', SubmitType::class)
            ->add('cancel', ResetType::class)
            ->add('captcha', Recaptcha3Type::class, [
                'constraints' => new Recaptcha3(),
                'locale' => 'de',
            ])
        ;

        if ($options['old']) {
            $builder
                ->add('initials', ChoiceType::class, [
                    'choices' => $options['initialOptions'],
                    'choice_label' => function ($choice, $key, $value) {
                        return $value;
                    }
                ])
                ->add('createdAt', DateTimeType::class)
            ;
        } else {
            $builder->add('initials', HiddenType::class);
        }

        $builder->get('numberPlate')
            ->addModelTransformer(new CallbackTransformer(
                function ($numberPlate) {
                    return $numberPlate;
                },
                function ($numberPlate) {
                    return strtoupper(preg_replace('/\s+/', '', $numberPlate));
                }
            ))
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => NumberPlate::class,
            'old' => false,
            'initialOptions' => [],
        ]);
    }
}
