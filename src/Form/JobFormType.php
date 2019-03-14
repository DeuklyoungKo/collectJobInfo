<?php

namespace App\Form;

use App\Entity\Job;
use App\lib\JobLib;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class JobFormType extends AbstractType
{
    /**
     * @var JobLib
     */
    private $jobLib;

    public function __construct(JobLib $jobLib)
    {
        $this->jobLib = $jobLib;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('title')
            ->add('company')
            ->add('location', ChoiceType::class, [
                'choices'  => $this->jobLib->makeLocationArray(),
            ])
            ->add('description', TextareaType::class, [
            ])
            ->add('link', TextareaType::class, [
            ])
            ->add('publishedAt', DateType::class, [
                'widget' => 'single_text',
                'format' => 'yyyy-MM-dd',
            ])
            ->add('jobId', TextType::class)
            ->add('applyState', ChoiceType::class, [
                'choices'  => $this->jobLib->makeJobApplyStateArray(),
            ])
            ->add('etc')
            ->add('source')
            ->add('Add', SubmitType::class, [
                'validation_groups' => false,
            ])

        ;
    }


    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Job::class,
        ]);
    }
}
