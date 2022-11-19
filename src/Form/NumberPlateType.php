<?php

namespace App\Form;

use App\Entity\NumberPlate;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class NumberPlateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('numberPlate')
            ->add('initials', HiddenType::class)
            ->add('file', FileType::class)
            ->add('submit', SubmitType::class)
            ->add('cancel', ResetType::class,)
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
