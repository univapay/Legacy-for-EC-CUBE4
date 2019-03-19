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

namespace Plugin\UpcPaymentPlugin\Form\Extension;

use Eccube\Entity\Order;
use Eccube\Form\Type\Shopping\OrderType;
use Eccube\Repository\PaymentRepository;
use Plugin\UpcPaymentPlugin\Service\Method\CreditCard;
use Plugin\UpcPaymentPlugin\Entity\Config;
use Plugin\UpcPaymentPlugin\Repository\ConfigRepository;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

/**
 * 注文手続き画面のFormを拡張し、カード入力フォームを追加する.
 * 支払い方法に応じてエクステンションを作成する.
 */
class CreditCardExtention extends AbstractTypeExtension
{
    /**
     * @var PaymentRepository
     */
    protected $paymentRepository;

    /**
     * @var ConfigRepository $UpcPaymentPluginConfig
     */
    protected $UpcPaymentPluginConfig;

    public function __construct(PaymentRepository $paymentRepository, ConfigRepository $UpcPaymentPluginConfig)
    {
        $this->paymentRepository = $paymentRepository;
        $this->Config = $UpcPaymentPluginConfig;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        // ShoppingController::checkoutから呼ばれる場合は, フォーム項目の定義をスキップする.
        if ($options['skip_add_form']) {
            return;
        }

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {
            /** @var Order $data */
            $form = $event->getForm();

            // 支払い方法が一致する場合
            $form->add('upc_payment_plugin_token', HiddenType::class, [
                'required' => false,
                'mapped' => true, // Orderエンティティに追加したカラムなので、mappedはtrue
            ]);
        });

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
            // サンプル決済では使用しないが、支払い方法に応じて処理を行う場合は
            // $event->getData()ではなく、$event->getForm()->getData()でOrderエンティティを取得できる

            /** @var Order $Order */
            $Order = $event->getForm()->getData();
            $Order->getPayment()->getId();
        });

        $UpcPaymentPluginConfig =$this->Config->get();
        //$UpcPaymentPluginConfig->getUseCvv();
    }

    /**
     * {@inheritdoc}
     */
    public function getExtendedType()
    {
        return OrderType::class;
    }
}
