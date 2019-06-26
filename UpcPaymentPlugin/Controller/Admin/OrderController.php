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

namespace Plugin\UpcPaymentPlugin\Controller\Admin;

use Eccube\Controller\AbstractController;
use Eccube\Entity\Order;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Plugin\UpcPaymentPlugin\entity\Config;
use Plugin\UpcPaymentPlugin\Repository\ConfigRepository;
use Plugin\UpcPaymentPlugin\Entity\PaymentStatus;
use Plugin\UpcPaymentPlugin\Repository\PaymentStatusRepository;

use Plugin\UpcPaymentPlugin\Service\Method\LinkCreditCard;
use Plugin\UpcPaymentPlugin\Service\Method\Alipay;
use Plugin\UpcPaymentPlugin\Service\Method\Wechat;


class OrderController extends AbstractController
{
    /**
     * @var Order
     */
    private $Order;
    /**
     * OrderController constructor.
     *
     * @param PaymentStatusRepository $paymentStatusRepository
     * @param ConfigRepository $ConfigRepository
     */

    public function __construct(
        PaymentStatusRepository $paymentStatusRepository,
        ConfigRepository $ConfigRepository
    ) {
        $this->paymentStatusRepository = $paymentStatusRepository;
        $this->configRepository = $ConfigRepository;
    }


    /**
     * 受注編集 > 決済のキャンセル処理
     *
     * @Method("POST")
     * @Route("/%eccube_admin_route%/upc_payment_plugin/order/cancel/{id}", requirements={"id" = "\d+"}, name="upc_payment_plugin_admin_order_cancel")
     */
    public function cancel(Request $request, Order $Order)
    {
        if ($request->isXmlHttpRequest() && $this->isTokenValid()) {
            //オーダー番号取得
            $request->get('id');

            //pluginconfigを呼び出す
            $UpcPaymentPluginConfig = $this->configRepository->get();

            //決済のチェック　
            if($Order->getUpcPaymentPluginPaymentStatus() == $this->paymentStatusRepository->find(PaymentStatus::CANCEL)){
              $this->addError('upc_payment_plugin.admin.order.cancel.executed', 'admin');
              return $this->json([]);
            }

            switch ($Order->getPayment()->getMethodClass()) {
              // クレジット
              case LinkCreditCard::class:
                $svid = 1;
                break;
              // alipay
              case Alipay::class:
                $svid = 6;
                break;
              // wechat
              case Wechat::class:
                $svid = 23;
                break;
              //paidy gw処理未実装なためコメントアウト
              // case Paidy::class:
              //   $svid = 25;
              //   break;
              default:
                $this->addError('upc_payment_plugin.admin.order.cancel.rejection', 'admin');
                return $this->json([]);

                break;
            }

            //リクエストクエリ作成
            $url = $UpcPaymentPluginConfig->getApiUrl() . '?';
            $url .= "sid=" . $UpcPaymentPluginConfig->getApiId();
            $url .= "&svid=";
            $url .= $svid;
            $url .= "&ptype=1&rt=4";
            $url .= "&job=CANCEL";
            $url .= "&pid=" . $Order->getUpcPaymentPluginPid();

            //取消リクエスト
            $rst = file_get_contents($url);

            parse_str($rst, $rst_cancel);
            //結果取得
            if($rst_cancel['rst'] == 1){
              // 決済ステータスを取消へ変更　キックバックでステータス変更を行うのでコメントアウト
              // $PaymentStatus = $this->paymentStatusRepository->find(PaymentStatus::CANCEL);
              // $Order->setUpcPaymentPluginPaymentStatus($PaymentStatus);

              $this->addSuccess('upc_payment_plugin.admin.order.cancel.success', 'admin');
            }else{
              $this->addError('upc_payment_plugin.admin.order.cancel.failed', 'admin');
            };
            //オーダー情報を更新
            $this->entityManager->persist($Order);
            $this->entityManager->flush($Order);

            return $this->json([]);
        }

        throw new BadRequestHttpException();
    }

    /**
     * 受注編集 > クレジット決済の実売処理
     *
     * @Method("POST")
     * @Route("/%eccube_admin_route%/upc_payment_plugin/order/change_sales/{id}", requirements={"id" = "\d+"}, name="upc_payment_plugin_admin_order_change_sales")
     */
    public function changeSales(Request $request, Order $Order)
    {
        if ($request->isXmlHttpRequest() && $this->isTokenValid()) {
            //オーダー番号取得
            $request->get('id');

            //pluginconfigを呼び出す
            $UpcPaymentPluginConfig = $this->configRepository->get();

            //決済のチェック　
          if($Order->getUpcPaymentPluginPaymentStatus() != $this->paymentStatusRepository->find(PaymentStatus::PROVISIONAL_SALES)){
              $this->addError('upc_payment_plugin.admin.order.change_sales.error', 'admin');
              return $this->json([]);
            }

            //リクエストクエリ作成
            $url = $UpcPaymentPluginConfig->getApiUrl() . '?';
            $url .= "sid=" . $UpcPaymentPluginConfig->getApiId();
            $url .= "&svid=1&ptype=1&rt=4";
            $url .= "&job=SALES";
            $url .= "&pid=" . $Order->getUpcPaymentPluginPid();

            //取消リクエスト
            $rst = file_get_contents($url);

            parse_str($rst, $rst_cancel);
            //結果取得
            if($rst_cancel['rst'] == 1){
              // 決済ステータスを取消へ変更　キックバックでステータス変更を行うのでコメントアウト
              // $PaymentStatus = $this->paymentStatusRepository->find(PaymentStatus::CANCEL);
              // $Order->setUpcPaymentPluginPaymentStatus($PaymentStatus);

              $this->addSuccess('upc_payment_plugin.admin.order.change_sales.success', 'admin');
            }else{
              $this->addError('upc_payment_plugin.admin.order.change_sales.failed', 'admin');
            };
            //オーダー情報を更新
            $this->entityManager->persist($Order);
            $this->entityManager->flush($Order);

            return $this->json([]);
        }

        throw new BadRequestHttpException();
    }
}
