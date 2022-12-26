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

namespace Plugin\UpcPaymentPlugin\Controller;

use Eccube\Controller\AbstractController;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Entity\Order;
use Eccube\Repository\Master\OrderStatusRepository;
use Eccube\Repository\Master\CustomerOrderStatusRepository;
use Eccube\Repository\OrderRepository;
use Eccube\Service\CartService;
use Eccube\Service\PurchaseFlow\PurchaseContext;
use Eccube\Service\PurchaseFlow\PurchaseFlow;
use Eccube\Service\ShoppingService;
use Eccube\Service\OrderStateMachine;
use Plugin\UpcPaymentPlugin\Entity\PaymentStatus;
use Plugin\UpcPaymentPlugin\Entity\CvsPaymentStatus;
use Plugin\UpcPaymentPlugin\Repository\PaymentStatusRepository;
use Plugin\UpcPaymentPlugin\Repository\CvsPaymentStatusRepository;
use Plugin\UpcPaymentPlugin\Service\Method\Convenience;
use Plugin\UpcPaymentPlugin\Service\Method\Bank;
use Plugin\UpcPaymentPlugin\Service\Method\Alipay;
use Plugin\UpcPaymentPlugin\Service\Method\Wechat;
use Plugin\UpcPaymentPlugin\Service\Method\Paidy;
use Plugin\UpcPaymentPlugin\entity\Config;
use Plugin\UpcPaymentPlugin\Repository\ConfigRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use Eccube\Service\MailService;


/**
 * リンク式決済の注文/戻る/完了通知を処理する.
 */
class PaymentController extends AbstractController
{
    /**
     * @var OrderRepository
     */
    protected $orderRepository;

    /**
     * @var OrderStatusRepository
     */
    protected $orderStatusRepository;

    /**
     * @var PaymentStatusRepository
     */
    protected $paymentStatusRepository;

    /**
     * @var CvsPaymentStatusRepository
     */
    protected $cvsPaymentStatusRepository;

    /**
     * @var PurchaseFlow
     */
    protected $purchaseFlow;

    /**
     * @var CartService
     */
    protected $cartService;

    /**
     * @var OrderStateMachine
     */
    protected $orderStateMachine;

    /**
     * @var MailService
     */
    protected $mailService;

    /**
     * PaymentController constructor.
     *
     * @param OrderRepository $orderRepository
     * @param OrderStatusRepository $orderStatusRepository
     * @param PaymentStatusRepository $paymentStatusRepository
     * @param CvsPaymentStatusRepository $CvsPaymentStatusRepository
     * @param PurchaseFlow $shoppingPurchaseFlow,
     * @param CartService $cartService
     * @param OrderStateMachine $orderStateMachine
     */
    public function __construct(
        OrderRepository $orderRepository,
        OrderStatusRepository $orderStatusRepository,
        PaymentStatusRepository $paymentStatusRepository,
        CvsPaymentStatusRepository $cvsPaymentStatusRepository,
        PurchaseFlow $shoppingPurchaseFlow,
        CartService $cartService,
        OrderStateMachine $orderStateMachine,
        MailService $mailService,
        ConfigRepository $ConfigRepository
    ) {
        $this->orderRepository = $orderRepository;
        $this->orderStatusRepository = $orderStatusRepository;
        $this->paymentStatusRepository = $paymentStatusRepository;
        $this->cvsPaymentStatusRepository = $cvsPaymentStatusRepository;
        $this->purchaseFlow = $shoppingPurchaseFlow;
        $this->cartService = $cartService;
        $this->orderStateMachine = $orderStateMachine;
        $this->mailService = $mailService;
        $this->configRepository = $ConfigRepository;
    }

    /**
     * @Route("/upc_payment_back", name="upc_payment_back")
     *
     * @param Request $request
     *
     * @return RedirectResponse
     */
    public function back(Request $request)
    {
        $orderNo = $request->get('no');
        $Order = $this->getOrderByNo($orderNo);

        if (!$Order) {
            throw new NotFoundHttpException();
        }

        if ($this->getUser() != $Order->getCustomer()) {
            throw new NotFoundHttpException();
        }

        // 受注ステータスを購入処理中へ変更
        $OrderStatus = $this->orderStatusRepository->find(OrderStatus::PROCESSING);
        $Order->setOrderStatus($OrderStatus);

        // 決済ステータスを未決済へ変更
        $PaymentStatus = $this->paymentStatusRepository->find(PaymentStatus::OUTSTANDING);
        $Order->setUpcPaymentPluginPaymentStatus($PaymentStatus);

        // purchaseFlow::rollbackを呼び出し, 購入処理をロールバックする.
        $this->purchaseFlow->rollback($Order, new PurchaseContext());

        // $this->entityManager->flush();

        return $this->redirectToRoute('shopping');
    }

    /**
     * 完了画面へ遷移する.
     *
     * @Route("/upc_payment_plugin_complete", name="upc_payment_plugin_complete")
     */
    public function complete(Request $request)
    {
        // 受注番号を受け取る
        $orderNo = $request->get('no');
        /** @var Order $Order */
        $Order = $this->orderRepository->findOneBy([
            'order_no' => $orderNo,
        ]);

        if (!$Order) {
            throw new NotFoundHttpException();
        }

        if ($this->getUser() != $Order->getCustomer()) {
            throw new NotFoundHttpException();
        }

        // カートを削除する
        $this->cartService->clear();

        // FIXME 完了画面を表示するため, 受注IDをセッションに保持する
        $this->session->set('eccube.front.shopping.order.id', $Order->getId());

        $this->entityManager->flush();

        return $this->redirectToRoute('shopping_complete');
    }

    /**
     * 結果通知URLを受け取る.
     *
     * @Route("/upc_payment_plugin_receive_complete", name="upc_payment_plugin_receive_complete")
     */
    public function receiveComplete(Request $request)
    {
        // 受注番号を受け取る
        $orderNo = $request->get('no');
        /** @var Order $Order */
        $Order = $this->orderRepository->findOneBy([
            'order_no' => $orderNo,
        ]);
        if (!$Order) {
            throw new NotFoundHttpException();
        }
        //決済失敗の場合はエラーにする
        $rst = $request->get('rst');
        if ($rst != 1) {
            throw new NotFoundHttpException();
        }

        /*ジョブによる処理の切り分け。
        *
        * auth PaymentStatus::PROVISIONAL_SALES
        * sales PaymentStatus::ACTUAL_SALES
        * capture PaymentStatus::ACTUAL_SALES
        *
        */
        $kbJob = $request->get('job');
        echo $kbJob;
        if ($kbJob == "AUTH"){
            // purchaseFlow::commitを呼び出し, 購入処理を完了させる.
            $this->purchaseFlow->commit($Order, new PurchaseContext());

            //注文日を設定
            $Order->setOrderDate(new \DateTime());
            // 決済ステータスを仮売上へ変更
            $PaymentStatus = $this->paymentStatusRepository->find(PaymentStatus::PROVISIONAL_SALES);
            $Order->setUpcPaymentPluginPaymentStatus($PaymentStatus);
            // 受注ステータス　入金済みを設定
            $OrderStatus = $this->orderStatusRepository->find(OrderStatus::PAID);
            $Order->setOrderStatus($OrderStatus);
            //入金日を設定
            $Order->setPaymentDate(new \DateTime());

            // 注文完了メールにメッセージを追加
            $Order->appendCompleteMailMessage('');


            // メール送信
            log_info('[注文処理] 注文メールの送信を行います.', [$Order->getId()]);
            $this->mailService->sendOrderMail($Order);
            $this->entityManager->flush();

        }elseif ($kbJob == "CAPTURE"){
            // purchaseFlow::commitを呼び出し, 購入処理を完了させる.
            $this->purchaseFlow->commit($Order, new PurchaseContext());

            //注文日を設定
            $Order->setOrderDate(new \DateTime());
            // 決済ステータスを実売上へ変更
            $PaymentStatus = $this->paymentStatusRepository->find(PaymentStatus::ACTUAL_SALES);
            $Order->setUpcPaymentPluginPaymentStatus($PaymentStatus);
            // 受注ステータス　入金済みを設定
            $OrderStatus = $this->orderStatusRepository->find(OrderStatus::PAID);
            $Order->setOrderStatus($OrderStatus);
            //入金日を設定
            $Order->setPaymentDate(new \DateTime());

            // 注文完了メールにメッセージを追加
            $Order->appendCompleteMailMessage('');


            // メール送信
            log_info('[注文処理] 注文メールの送信を行います.', [$Order->getId()]);
            $this->mailService->sendOrderMail($Order);
            $this->entityManager->flush();

        }elseif ($kbJob == "SALES"){
          // 決済ステータスを実売上へ変更
          $PaymentStatus = $this->paymentStatusRepository->find(PaymentStatus::ACTUAL_SALES);
          $Order->setUpcPaymentPluginPaymentStatus($PaymentStatus);
        }elseif ($kbJob == "CANCEL") {
          // 受注ステータス　取り消しを設定
          $PaymentStatus = $this->paymentStatusRepository->find(PaymentStatus::CANCEL);
          $Order->setUpcPaymentPluginPaymentStatus($PaymentStatus);
          $OrderStatus = $this->orderStatusRepository->find(OrderStatus::CANCEL);
          $Order->setOrderStatus($OrderStatus);
        }


        //決済番号を保存
        $pid = $request->get('pid');
        $Order->setUpcPaymentPluginPid($pid);

        $this->entityManager->persist($Order);
        $this->entityManager->flush($Order);

        return new Response('OK!!');
    }

    /**
     * 結果通知URLを受け取る(alipay).
     *
     * @Route("/upc_payment_plugin_receive_alipay", name="upc_payment_plugin_receive_alipay")
     */
    public function receiveAlipayStatus(Request $request)
    {
        // 受注番号を受け取る
        $orderNo = $request->get('no');
        /** @var Order $Order */
        $Order = $this->orderRepository->findOneBy([
            'order_no' => $orderNo,
        ]);

        if (!$Order) {
            throw new NotFoundHttpException();
        }
        //決済失敗の場合はエラーにする
        $rst = $request->get('rst');
        if ($rst != 1) {
            throw new NotFoundHttpException();
        }
        //alipayの決済か確認
        if ($Order->getPayment()->getMethodClass() !== Alipay::class) {
            throw new BadRequestHttpException();
        }

        $kbJob = $request->get('job');
        echo $kbJob;

        switch ($kbJob) {
            // 決済失敗
            case "CANCEL":
                // 受注ステータス　取り消しを設定
                $PaymentStatus = $this->paymentStatusRepository->find(PaymentStatus::CANCEL);
                $Order->setUpcPaymentPluginPaymentStatus($PaymentStatus);
                $OrderStatus = $this->orderStatusRepository->find(OrderStatus::CANCEL);
                $Order->setOrderStatus($OrderStatus);
                break;
            // 決済完了
            case "SALES":
                // purchaseFlow::commitを呼び出し, 購入処理を完了させる.
                $this->purchaseFlow->commit($Order, new PurchaseContext());

                //注文日を設定
                $Order->setOrderDate(new \DateTime());
                // 決済ステータスを実売上へ変更
                $PaymentStatus = $this->paymentStatusRepository->find(PaymentStatus::SALES);
                $Order->setUpcPaymentPluginPaymentStatus($PaymentStatus);
                // 受注ステータス　入金済みを設定
                $OrderStatus = $this->orderStatusRepository->find(OrderStatus::PAID);
                $Order->setOrderStatus($OrderStatus);
                //入金日を設定
                $Order->setPaymentDate(new \DateTime());

                // 注文完了メールにメッセージを追加
                $Order->appendCompleteMailMessage('');


                // メール送信
                log_info('[注文処理] 注文メールの送信を行います.', [$Order->getId()]);
                $this->mailService->sendOrderMail($Order);
                $this->entityManager->flush();


                break;
            default:
                break;
        }
        //決済番号を保存
        $pid = $request->get('pid');
        $Order->setUpcPaymentPluginPid($pid);

        $this->entityManager->flush();

        return new Response('OK!!');
    }

    /**
     * 結果通知URLを受け取る(wechat).
     *
     * @Route("/upc_payment_plugin_receive_wechat", name="upc_payment_plugin_receive_wechat")
     */
    public function receiveWechatStatus(Request $request)
    {
        // 受注番号を受け取る
        $orderNo = $request->get('no');
        /** @var Order $Order */
        $Order = $this->orderRepository->findOneBy([
            'order_no' => $orderNo,
        ]);

        if (!$Order) {
            throw new NotFoundHttpException();
        }
        //決済失敗の場合はエラーにする
        $rst = $request->get('rst');
        if ($rst != 1) {
            throw new NotFoundHttpException();
        }
        //wechatの決済確認
        if ($Order->getPayment()->getMethodClass() !== Wechat::class) {
            throw new BadRequestHttpException();
        }

        $kbJob = $request->get('job');
        echo $kbJob;

        switch ($kbJob) {
            // 決済失敗
            case "CANCEL":
                // 受注ステータス　取り消しを設定
                $PaymentStatus = $this->paymentStatusRepository->find(PaymentStatus::CANCEL);
                $Order->setUpcPaymentPluginPaymentStatus($PaymentStatus);
                $OrderStatus = $this->orderStatusRepository->find(OrderStatus::CANCEL);
                $Order->setOrderStatus($OrderStatus);
                break;
            // 決済完了
            case "SALES":
                // purchaseFlow::commitを呼び出し, 購入処理を完了させる.
                $this->purchaseFlow->commit($Order, new PurchaseContext());

                //注文日を設定
                $Order->setOrderDate(new \DateTime());
                // 決済ステータスを実売上へ変更
                $PaymentStatus = $this->paymentStatusRepository->find(PaymentStatus::SALES);
                $Order->setUpcPaymentPluginPaymentStatus($PaymentStatus);
                // 受注ステータス　入金済みを設定
                $OrderStatus = $this->orderStatusRepository->find(OrderStatus::PAID);
                $Order->setOrderStatus($OrderStatus);
                //入金日を設定
                $Order->setPaymentDate(new \DateTime());

                // 注文完了メールにメッセージを追加
                $Order->appendCompleteMailMessage('');


                // メール送信
                log_info('[注文処理] 注文メールの送信を行います.', [$Order->getId()]);
                $this->mailService->sendOrderMail($Order);
                $this->entityManager->flush();

                break;
            default:
                break;

        }
        //決済番号を保存
        $pid = $request->get('pid');
        $Order->setUpcPaymentPluginPid($pid);

        $this->entityManager->flush();

        return new Response('OK!!');
    }

    /**
     * 結果通知URLを受け取る(Paidy).
     *
     * @Route("/upc_payment_plugin_receive_paidy", name="upc_payment_plugin_receive_paidy")
     */
    public function receivePaidyStatus(Request $request)
    {
        // 受注番号を受け取る
        $orderNo = $request->get('no');
        /** @var Order $Order */
        $Order = $this->orderRepository->findOneBy([
            'order_no' => $orderNo,
        ]);

        if (!$Order) {
            throw new NotFoundHttpException();
        }
        //決済失敗の場合はエラーにする
        $rst = $request->get('rst');
        if ($rst != 1) {
            throw new NotFoundHttpException();
        }
        //paidy決済の確認
        if ($Order->getPayment()->getMethodClass() !== Paidy::class) {
            throw new BadRequestHttpException();
        }

        $kbJob = $request->get('job');
        echo $kbJob;

        switch ($kbJob) {
            // 決済取消
            case "REFUND":
            case "refund":
                // 受注ステータス　取り消しを設定
                $PaymentStatus = $this->paymentStatusRepository->find(PaymentStatus::CANCEL);
                $Order->setUpcPaymentPluginPaymentStatus($PaymentStatus);
                $OrderStatus = $this->orderStatusRepository->find(OrderStatus::CANCEL);
                $Order->setOrderStatus($OrderStatus);
                break;
            // 決済取消
            case "CLOSE":
            case "close":
                // 受注ステータス　取り消しを設定
                $PaymentStatus = $this->paymentStatusRepository->find(PaymentStatus::CANCEL);
                $Order->setUpcPaymentPluginPaymentStatus($PaymentStatus);
                $OrderStatus = $this->orderStatusRepository->find(OrderStatus::CANCEL);
                $Order->setOrderStatus($OrderStatus);
                break;
            // 決済完了
            case "AUTH":
            case "auth":
                // purchaseFlow::commitを呼び出し, 購入処理を完了させる.
                $this->purchaseFlow->commit($Order, new PurchaseContext());

                //注文日を設定
                $Order->setOrderDate(new \DateTime());
                // 決済ステータスを仮売上へ変更
                $PaymentStatus = $this->paymentStatusRepository->find(PaymentStatus::PROVISIONAL_SALES);
                $Order->setUpcPaymentPluginPaymentStatus($PaymentStatus);
                // 受注ステータス　入金済みを設定
                $OrderStatus = $this->orderStatusRepository->find(OrderStatus::PAID);
                $Order->setOrderStatus($OrderStatus);
                //入金日を設定
                $Order->setPaymentDate(new \DateTime());

                // 注文完了メールにメッセージを追加
                $Order->appendCompleteMailMessage('');


                // メール送信
                log_info('[注文処理] 注文メールの送信を行います.', [$Order->getId()]);
                $this->mailService->sendOrderMail($Order);
                $this->entityManager->flush();

                break;
            case "CAPTURE":
            case "capture":
                //実売り時の処理
                // 決済ステータスを仮売上へ変更
                $PaymentStatus = $this->paymentStatusRepository->find(PaymentStatus::SALES);
                $Order->setUpcPaymentPluginPaymentStatus($PaymentStatus);
                break;
            default:
                break;

        }
        //決済番号を保存
        $pid = $request->get('pid');
        $Order->setUpcPaymentPluginPid($pid);

        $this->entityManager->flush();

        return new Response('OK!!');
    }

    /**
     * 結果通知URLを受け取る(bank).
     *
     * @Route("/upc_payment_plugin_receive_bank", name="upc_payment_plugin_receive_bank")
     */
    public function receiveBankStatus(Request $request)
    {
        // 受注番号を受け取る
        $orderNo = $request->get('no');
        /** @var Order $Order */
        $Order = $this->orderRepository->findOneBy([
            'order_no' => $orderNo,
        ]);

        if (!$Order) {
            throw new NotFoundHttpException();
        }
        //決済失敗の場合はエラーにする
        $rst = $request->get('rst');
        if ($rst == 2) {
            throw new NotFoundHttpException();
        }
        //オート銀振り決済の確認
        if ($Order->getPayment()->getMethodClass() !== Bank::class) {
            throw new BadRequestHttpException();
        }

        $kbJob = $request->get('job');
        echo $kbJob;

        switch ($kbJob) {
            case "EBPRERENTAL":
                //注文日を設定
                $Order->setOrderDate(new \DateTime());
                // 決済ステータスをEBPRERENTALへ変更
                $PaymentStatus = $this->paymentStatusRepository->find(PaymentStatus::EBPRERENTAL);
                $Order->setUpcPaymentPluginPaymentStatus($PaymentStatus);
                // 受注ステータス　決済処理中を設定
                $OrderStatus = $this->orderStatusRepository->find(OrderStatus::PENDING);
                $Order->setOrderStatus($OrderStatus);

                break;
            case "EBRENTAL":
                // 決済ステータスを貸出へ変更
                $PaymentStatus = $this->paymentStatusRepository->find(PaymentStatus::EBRENTAL);
                $Order->setUpcPaymentPluginPaymentStatus($PaymentStatus);
                // 受注ステータス　新規注文を設定
                $OrderStatus = $this->orderStatusRepository->find(OrderStatus::NEW);
                $Order->setOrderStatus($OrderStatus);

                //会員オーダーステータスに注文未完了　8　を設定
                // $CustomerOrderStatus = $this->CustomerOrderStatusRepository->find(name::8);
                // $Order->setCustomerOrderStatus($CustomerOrderStatus);
                // var_dump($Order);

                // 注文完了メールにメッセージを追加
                $Order->appendCompleteMailMessage('');

                // メール送信
                log_info('[注文処理] 注文メールの送信を行います.', [$Order->getId()]);
                $this->mailService->sendOrderMail($Order);
                $this->entityManager->flush();

                break;
            case "EBTRANSFER":
                // purchaseFlow::commitを呼び出し, 購入処理を完了させる.
                $this->purchaseFlow->commit($Order, new PurchaseContext());

                // 決済ステータスを入金済みへ変更
                $PaymentStatus = $this->paymentStatusRepository->find(PaymentStatus::EBTRANSFER);
                $Order->setUpcPaymentPluginPaymentStatus($PaymentStatus);
                // 受注ステータス　rst=1は入金済みを設定 それ以外。超過不足の場合は新規受付を設定
                if($rst != 1){
                    $OrderStatus = $this->orderStatusRepository->find(OrderStatus::NEW);
                    $Order->setOrderStatus($OrderStatus);
                }else{
                    $OrderStatus = $this->orderStatusRepository->find(OrderStatus::PAID);
                    $Order->setOrderStatus($OrderStatus);
                }
                //入金日を設定
                $Order->setPaymentDate(new \DateTime());

                break;

            default:
                break;

        }
        //決済番号を保存
        $pid = $request->get('pid');
        $Order->setUpcPaymentPluginPid($pid);

        // $this->entityManager->flush();
        $this->entityManager->persist($Order);
        $this->entityManager->flush($Order);

        return new Response('OK!!');
    }


    /**
     * 結果通知URLを受け取る(コンビニ決済).
     *
     * @Route("/upc_payment_plugin_receive_cvs", name="upc_payment_plugin_receive_cvs")
     */
    public function receiveCvsStatus(Request $request)
    {
        // 受注番号を受け取る
        $orderNo = $request->get('no');
        /** @var Order $Order */
        $Order = $this->orderRepository->findOneBy([
            'order_no' => $orderNo,
        ]);

        if (!$Order) {
            throw new NotFoundHttpException();
        }
        //決済失敗の場合はエラーにする
        $rst = $request->get('rst');
        if ($rst != 1) {
            throw new NotFoundHttpException();
        }

        if ($Order->getPayment()->getMethodClass() !== Convenience::class) {
            throw new BadRequestHttpException();
        }

        $cvs_status = $request->get('job');
        echo "csv ";
        switch ($cvs_status) {
            // 決済申込
            case "CAPTURE":
            case "capture":
	            //注文日を設定
	            $Order->setOrderDate(new \DateTime());

                // 受注ステータスを決済申込へ変更
                $PaymentStatus = $this->paymentStatusRepository->find(PaymentStatus::REQUEST);
                $Order->setUpcPaymentPluginPaymentStatus($PaymentStatus);
                // 受注ステータス　新規注文を設定
                $OrderStatus = $this->orderStatusRepository->find(OrderStatus::NEW);
                $Order->setOrderStatus($OrderStatus);

                // コンビニステータス　新規注文を設定
                $CvsOrderStatus = $this->cvsPaymentStatusRepository->find(CvsPaymentStatus::REQUEST);
                $Order->setUpcPaymentPluginCvsPaymentStatus($CvsOrderStatus);
				echo $cvs_status;

                // メール送信
                log_info('[注文処理] 注文メールの送信を行います.', [$Order->getId()]);
                $this->mailService->sendOrderMail($Order);
                $this->entityManager->flush();

                break;
            // 決済完了
            case "SALES":
            case "sales":
                // 受注ステータスを決済申込へ変更
                $PaymentStatus = $this->paymentStatusRepository->find(PaymentStatus::SALES);
                $Order->setUpcPaymentPluginPaymentStatus($PaymentStatus);
                // 受注ステータス　新規注文を設定
                $OrderStatus = $this->orderStatusRepository->find(OrderStatus::PAID);
                $Order->setOrderStatus($OrderStatus);
                // コンビニステータス　新規注文を設定
                $CvsOrderStatus = $this->cvsPaymentStatusRepository->find(CvsPaymentStatus::COMPLETE);
                $Order->setUpcPaymentPluginCvsPaymentStatus($CvsOrderStatus);
				echo $cvs_status;

                //入金日を設定
                $Order->setPaymentDate(new \DateTime());
                $this->entityManager->flush();

            default:
				break;
        }
        //決済番号を保存
        $pid = $request->get('pid');
        $Order->setUpcPaymentPluginPid($pid);

        $this->entityManager->flush();

        return new Response('OK!!');
    }

    /**
     * 注文番号で受注を検索する.
     *
     * @param $orderNo
     *
     * @return Order
     */
    private function getOrderByNo($orderNo)
    {
        /** @var OrderStatus $pendingOrderStatus */
        $pendingOrderStatus = $this->orderStatusRepository->find(OrderStatus::PENDING);

        $outstandingPaymentStatus = $this->paymentStatusRepository->find(PaymentStatus::OUTSTANDING);

        /** @var Order $Order */
        $Order = $this->orderRepository->findOneBy([
            'order_no' => $orderNo,
            'OrderStatus' => $pendingOrderStatus,
            'UpcPaymentPluginPaymentStatus' => $outstandingPaymentStatus,
        ]);

        return $Order;
    }
}
