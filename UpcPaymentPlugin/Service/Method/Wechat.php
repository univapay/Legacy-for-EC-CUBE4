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

namespace Plugin\UpcPaymentPlugin\Service\Method;

use Eccube\Entity\Master\OrderStatus;
use Eccube\Entity\Order;
use Eccube\Exception\ShoppingException;
use Eccube\Repository\Master\OrderStatusRepository;
use Eccube\Service\Payment\PaymentDispatcher;
use Eccube\Service\Payment\PaymentMethodInterface;
use Eccube\Service\Payment\PaymentResult;
use Eccube\Service\PurchaseFlow\PurchaseContext;
use Eccube\Service\PurchaseFlow\PurchaseFlow;
use Plugin\UpcPaymentPlugin\entity\Config;
use Plugin\UpcPaymentPlugin\Repository\ConfigRepository;
use Plugin\UpcPaymentPlugin\Entity\PaymentStatus;
use Plugin\UpcPaymentPlugin\Repository\PaymentStatusRepository;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Wechatの決済処理を行う
 */
class Wechat implements PaymentMethodInterface
{
    /**
     * @var Order
     */
    private $Order;

    /**
     * @var FormInterface
     */
    private $form;

    /**
     * @var OrderStatusRepository
     */
    private $orderStatusRepository;

    /**
     * @var PaymentStatusRepository
     */
    private $paymentStatusRepository;

    /**
     * @var PurchaseFlow
     */
    private $purchaseFlow;

    /**
     * LinkCreditCard constructor.
     *
     * @param OrderStatusRepository $orderStatusRepository
     * @param PaymentStatusRepository $paymentStatusRepository
     * @param PurchaseFlow $shoppingPurchaseFlow
     */
    public function __construct(
        OrderStatusRepository $orderStatusRepository,
        PaymentStatusRepository $paymentStatusRepository,
        PurchaseFlow $shoppingPurchaseFlow,
        ConfigRepository $ConfigRepository
    ) {
        $this->orderStatusRepository = $orderStatusRepository;
        $this->paymentStatusRepository = $paymentStatusRepository;
        $this->purchaseFlow = $shoppingPurchaseFlow;
        $this->configRepository = $ConfigRepository;
    }

    /**
     * 注文確認画面遷移時に呼び出される.
     *
     * リンク式は使用しない.
     *
     * @return PaymentResult|void
     */
    public function verify()
    {
        $result = new PaymentResult();
        $result->setSuccess(true);

        return $result;
    }

    /**
     * 注文時に呼び出される.
     *
     * 決済サーバのカード入力画面へリダイレクトする.
     *
     * @return PaymentDispatcher
     *
     * @throws ShoppingException
     */
    public function apply()
    {
        // 受注ステータスを決済処理中へ変更
        $OrderStatus = $this->orderStatusRepository->find(OrderStatus::PENDING);
        $this->Order->setOrderStatus($OrderStatus);

        // 決済ステータスを未決済へ変更
        $PaymentStatus = $this->paymentStatusRepository->find(PaymentStatus::OUTSTANDING);
        $this->Order->setUpcPaymentPluginPaymentStatus($PaymentStatus);

        // purchaseFlow::prepareを呼び出し, 購入処理を進める.
        $this->purchaseFlow->prepare($this->Order, new PurchaseContext());

        //pluginconfigを呼び出す
        $UpcPaymentPluginConfig = $this->configRepository->get();

        // 決済サーバのカード入力画面へリダイレクトする.
        $url = $UpcPaymentPluginConfig->getApiUrl() . '?no=' . $this->Order->getOrderNo();
        $url .= "&sid=" . $UpcPaymentPluginConfig->getApiId();
        $url .= "&svid=23&ptype=8";
        $url .= "&job=REQUEST";
        $url .= "&siam1=" . $this->Order->getTotalPrice();
        $url .= "&em=" . $this->Order->getEmail();
        $url .= "&tn=" . $this->Order->getPhoneNumber();
        $url .= "&method=WEB";
        $url .= "&lang=cn";
        $url .= "&sucd=p_return";


        $response = new RedirectResponse($url);
        $dispatcher = new PaymentDispatcher();
        $dispatcher->setResponse($response);

        return $dispatcher;

    }

    /**
     * 注文時に呼び出される.
     * リンク式の場合, applyで決済サーバのカード入力画面へ遷移するため, checkoutは使用しない.
     *
     * @return PaymentResult
     */
    public function checkout()
    {
        $result = new PaymentResult();
        $result->setSuccess(true);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function setFormType(FormInterface $form)
    {
        $this->form = $form;
    }

    /**
     * {@inheritdoc}
     */
    public function setOrder(Order $Order)
    {
        $this->Order = $Order;
    }
}
