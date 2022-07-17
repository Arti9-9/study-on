<?php

namespace App\Form;

use App\Entity\Course;
use Symfony\Component\DomCrawler\Field\TextareaFormField;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;

class CourseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('characterCode', TextType::class, [
                'label' => 'Код курса',
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
            ->add('title', TextType::class, [
                'label' => 'Названиие',
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
            ->add('description', TextareaType::class, [
                'required' => false,
                'label' => 'Описание',
                'constraints' => [
                    new Length([
                        'max' => 1000,
                        'maxMessage' => 'Превышено максималльное значение символов',
                    ]),
                ],

            ])
            ->add('price', MoneyType::class, [
                'label' => 'Цена',
                'data' => $options['price'],
                'mapped' => false,
                'constraints' => [
                    new NotBlank(),
                ],
                'currency' => 'rub',
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Тип курса',
                'mapped' => false,
                'data' => $options['type'],
                'constraints' => [
                    new NotBlank(),
                ],
                'choices' => [
                    'Бесплатный' => 'free',
                    'Аренда' => 'rent',
                    'Покупка' => 'buy'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Course::class,
            'price' => 0.0,
            'type' => 'rent'
        ]);
    }
}
