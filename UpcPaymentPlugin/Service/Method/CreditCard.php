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
use Eccube\Repository\Master\OrderStatusRepository;
use Eccube\Service\Payment\PaymentDispatcher;
use Eccube\Service\Payment\PaymentMethodInterface;
use Eccube\Service\Payment\PaymentResult;
use Eccube\Service\PurchaseFlow\PurchaseContext;
use Eccube\Service\PurchaseFlow\PurchaseFlow;
use Plugin\UpcPaymentPlugin\Entity\PaymentStatus;
use Plugin\UpcPaymentPlugin\Repository\PaymentStatusRepository;
use Plugin\UpcPaymentPlugin\Repository\ConfigRepository;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\Form;

/**
 * クレジットカード(トークン決済)の決済処理を行う.
 */
class CreditCard implements PaymentMethodInterface
{
    /**
     * @var Order
     */
    protected $Order;

    /**
     * @var FormInterface
     */
    protected $form;

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
     * CreditCard constructor.
     *
     * @param OrderStatusRepository $orderStatusRepository
     * @param PaymentStatusRepository $paymentStatusRepository
     * @param PurchaseFlow $shoppingPurchaseFlow
     * @param ConfigRepository $ConfigRepository
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
     * クレジットカードの有効性チェックを行う.
     *
     * @return PaymentResult
     *
     * @throws \Eccube\Service\PurchaseFlow\PurchaseException
     */
    public function verify()
    {
        // token情報の取得とオーダー情報へのセット
        $this->setFormType($this->form);
        if (true) {
            $result = new PaymentResult();
            $result->setSuccess(true);

        } else {
            $result = new PaymentResult();
            $result->setSuccess(false);
            $result->setErrors([trans('upc_payment_plugin.shopping.verify.error')]);
        }

        return $result;
    }

    /**
     * 注文時に呼び出される.
     *
     * 受注ステータス, 決済ステータスを更新する.
     * ここでは決済サーバとの通信は行わない.
     *
     * @return PaymentDispatcher|null
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
    }

    /**
     * 注文時に呼び出される.
     *
     * クレジットカードの決済処理を行う.
     *
     * @return PaymentResult
     */
    public function checkout()
    {

        //pluginconfigを呼び出す
        $UpcPaymentPluginConfig = $this->configRepository->get();

        //決済サーバに仮売上のリクエスト送る
        $token = 'https://gw.ccps.jp/memberpay.aspx';
        $token .= '?no=' . $this->Order->getOrderNo();
        $token .= "&sid=" . $UpcPaymentPluginConfig->getApiId();
        //結果をjson＆キックバックで取得　
        $token .= "&svid=1&ptype=1&rt=4";
        $token .= "&upcmemberid=" . $this->Order->getUpcPaymentPluginToken();
        $token .= "&job=";
        $token .= $UpcPaymentPluginConfig->getCreditJob() == 0 ? "CAPTURE" : "AUTH";
        $token .= "&siam1=" . $this->Order->getTotalPrice();
        $token .= "&em=" . $this->Order->getEmail();
        $token .= "&tn=" . $this->Order->getPhoneNumber();

        log_info('[リクエストurl] リクエスト' .$token, []);

        //決済システムとの通信
        $response = file_get_contents($token);
        log_info('[リクエスト結果] 結果.', [$response]);

        parse_str($response,$rst);

        //結果判定
        if ($rst['rst'] == "1") {

            // purchaseFlow::commitを呼び出し, 購入処理を完了させる.
            $this->purchaseFlow->commit($this->Order, new PurchaseContext());

            // 受注ステータスを購入処理中へ変更
            $OrderStatus = $this->orderStatusRepository->find(OrderStatus::PROCESSING);
            $this->Order->setOrderStatus($OrderStatus);

            if($UpcPaymentPluginConfig->getCreditJob() == 0){
              // CAPTURE決済ステータスを売上へ変更
              $PaymentStatus = $this->paymentStatusRepository->find(PaymentStatus::SALES);
              $this->Order->setUpcPaymentPluginPaymentStatus($PaymentStatus);
            }else{
              // AUTH決済ステータスを仮売上へ変更
              $PaymentStatus = $this->paymentStatusRepository->find(PaymentStatus::PROVISIONAL_SALES);
              $this->Order->setUpcPaymentPluginPaymentStatus($PaymentStatus);
            }

            // 注文完了画面/注文完了メールにメッセージを追加
            // $this->Order->appendCompleteMessage('トークン -> '.$token);
            // $this->Order->appendCompleteMailMessage('トークン -> '.$token);


            $result = new PaymentResult();
            $result->setSuccess(true);
        } else {
            // 受注ステータスを購入処理中へ変更
            $OrderStatus = $this->orderStatusRepository->find(OrderStatus::PROCESSING);
            $this->Order->setOrderStatus($OrderStatus);

            // 決済ステータスを未決済へ変更
            $PaymentStatus = $this->paymentStatusRepository->find(PaymentStatus::OUTSTANDING);
            $this->Order->setUpcPaymentPluginPaymentStatus($PaymentStatus);

            // 失敗時はpurchaseFlow::rollbackを呼び出す.
            $this->purchaseFlow->rollback($this->Order, new PurchaseContext());

            $result = new PaymentResult();
            $result->setSuccess(false);

            //T52G01　T52G13　T52G92　が含まれる場合は、認証エラーの表示

            if(strpos($rst['ec'],'T52G01') !== false || strpos($rst['ec'],'T52G13') || strpos($rst['ec'],'T52G92') ){
                $result->setErrors([trans('upc_payment_plugin.shopping.checkout.error.Authentication')]);
            }else{
                $result->setErrors([trans('upc_payment_plugin.shopping.checkout.error')."エラーコード : ".$rst['ec']]);
            }

            if($rst['ec'] ){

            }else{
            }

        }

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
