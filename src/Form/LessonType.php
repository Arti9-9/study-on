<?php

namespace App\Form;

use App\Entity\Lesson;
use App\Form\DataTransformer\CourseToStringTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;

class LessonType extends AbstractType
{
    private $transformer;

    public function __construct(CourseToStringTransformer $transformer)
    {
        $this->transformer = $transformer;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Название',
                'constraints' => [
                    new Length([
                        'max' => 255,
                        'maxMessage' => 'Превышено максималльное значение символов',
                    ]),
                    new NotBlank([
                        'message' => 'Поле не может быть пустым',
                        ]),
                ],
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Контент',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Поле не может быть пустым',
                    ]),
                ],
            ])
            ->add('number', NumberType::class, [
                'label' => 'Порядковый номер',
                'constraints' => [
                    new Range([
                        'notInRangeMessage' => 'Значение поля должно быть в пределах от {{ min }} до {{ max }}',
                        'min' => 1,
                        'max' => 10000,
                    ]),
                    new NotBlank([
                        'message' => 'Поле не может быть пустым',
                    ]),
                ],
            ])
            ->add('course', HiddenType::class);

        $builder->get('course')
            ->addModelTransformer($this->transformer);

    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Lesson::class,
            'course' => null,
        ]);
    }
}
