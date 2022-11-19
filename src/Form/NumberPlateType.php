<?php

namespace App\Form;

use App\Entity\NumberPlate;
use Karser\Recaptcha3Bundle\Form\Recaptcha3Type;
use Karser\Recaptcha3Bundle\Validator\Constraints\Recaptcha3;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotNull;

class NumberPlateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('numberPlate')
            ->add('initials', HiddenType::class)
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
        ]);
    }
}
