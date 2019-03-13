<?php

/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) LOCKON CO.,LTD. All Rights Reserved.
 *
 * http://www.lockon.co.jp/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Plugin\UpcPaymentPlugin\Form\Type\Admin;

use Plugin\UpcPaymentPlugin\Entity\Config;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class ConfigType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('api_id', TextType::class, [
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->add('api_password', TextType::class, [
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->add('api_url', UrlType::class, [
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->add('credit_job', ChoiceType::class, [
                'choices'  => [
                    '仮実同時' => '0',
                    '仮売' => '1',
                ],
            ])
            ->add('use_cvv', ChoiceType::class, [
                'choices'  => [
                    '表示' => '0',
                    '非表示' => '1',
                ],
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->add('use_split', ChoiceType::class, [
                'choices'  => [
                    '表示' => '0',
                    '非表示' => '1',
                ],
                'constraints' => [
                    new NotBlank(),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Config::class,
        ]);
    }
}
