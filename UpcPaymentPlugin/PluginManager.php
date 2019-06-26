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

namespace Plugin\UpcPaymentPlugin;

use Eccube\Entity\Payment;
use Eccube\Plugin\AbstractPluginManager;
use Eccube\Repository\PaymentRepository;
use Plugin\UpcPaymentPlugin\Entity\Config;
use Plugin\UpcPaymentPlugin\Entity\PaymentStatus;
use Plugin\UpcPaymentPlugin\Entity\CvsPaymentStatus;
use Plugin\UpcPaymentPlugin\Entity\CvsType;
use Plugin\UpcPaymentPlugin\Service\Method\LinkCreditCard;
use Plugin\UpcPaymentPlugin\Service\Method\Alipay;
use Plugin\UpcPaymentPlugin\Service\Method\Wechat;
use Plugin\UpcPaymentPlugin\Service\Method\Paidy;
use Plugin\UpcPaymentPlugin\Service\Method\Bank;
use Plugin\UpcPaymentPlugin\Service\Method\Convenience;
use Plugin\UpcPaymentPlugin\Service\Method\CreditCard;
use Symfony\Component\DependencyInjection\ContainerInterface;

class PluginManager extends AbstractPluginManager
{
    public function enable(array $meta, ContainerInterface $container)
    {
        // $this->createTokenPayment($container);
        $this->createLinkPayment($container);
        // $this->createCvsPayment($container);
        $this->createConfig($container);
        $this->createPaymentStatuses($container);
        $this->createCvsPaymentStatuses($container);
        $this->createCvsTypes($container);
        $this->createAlipayPayment($container);
        $this->createWechatPayment($container);
        $this->createPaidyPayment($container);
        $this->createBankPayment($container);
    }

    private function createTokenPayment(ContainerInterface $container)
    {
        $entityManager = $container->get('doctrine')->getManager();
        $paymentRepository = $container->get(PaymentRepository::class);

        $Payment = $paymentRepository->findOneBy([], ['sort_no' => 'DESC']);
        $sortNo = $Payment ? $Payment->getSortNo() + 1 : 1;

        $Payment = $paymentRepository->findOneBy(['method_class' => CreditCard::class]);
        if ($Payment) {
            return;
        }

        $Payment = new Payment();
        $Payment->setCharge(0);
        $Payment->setSortNo($sortNo);
        $Payment->setVisible(true);
        $Payment->setMethod('クレジット決済(トークン)');
        $Payment->setMethodClass(CreditCard::class);

        $entityManager->persist($Payment);
        $entityManager->flush($Payment);
    }

    private function createLinkPayment(ContainerInterface $container)
    {
        $entityManager = $container->get('doctrine')->getManager();
        $paymentRepository = $container->get(PaymentRepository::class);

        $Payment = $paymentRepository->findOneBy([], ['sort_no' => 'DESC']);
        $sortNo = $Payment ? $Payment->getSortNo() + 1 : 1;

        $Payment = $paymentRepository->findOneBy(['method_class' => LinkCreditCard::class]);
        if ($Payment) {
            return;
        }

        $Payment = new Payment();
        $Payment->setCharge(0);
        $Payment->setSortNo($sortNo);
        $Payment->setVisible(true);
        $Payment->setMethod('クレジット決済');
        $Payment->setMethodClass(LinkCreditCard::class);

        $entityManager->persist($Payment);
        $entityManager->flush($Payment);
    }

    private function createAlipayPayment(ContainerInterface $container)
    {
        $entityManager = $container->get('doctrine')->getManager();
        $paymentRepository = $container->get(PaymentRepository::class);

        $Payment = $paymentRepository->findOneBy([], ['sort_no' => 'DESC']);
        $sortNo = $Payment ? $Payment->getSortNo() + 1 : 1;

        $Payment = $paymentRepository->findOneBy(['method_class' => Alipay::class]);
        if ($Payment) {
            return;
        }

        $Payment = new Payment();
        $Payment->setCharge(0);
        $Payment->setSortNo($sortNo);
        $Payment->setVisible(true);
        $Payment->setMethod('Alipay');
        $Payment->setMethodClass(Alipay::class);

        $entityManager->persist($Payment);
        $entityManager->flush($Payment);
    }

    private function createWechatPayment(ContainerInterface $container)
    {
        $entityManager = $container->get('doctrine')->getManager();
        $paymentRepository = $container->get(PaymentRepository::class);

        $Payment = $paymentRepository->findOneBy([], ['sort_no' => 'DESC']);
        $sortNo = $Payment ? $Payment->getSortNo() + 1 : 1;

        $Payment = $paymentRepository->findOneBy(['method_class' => Wechat::class]);
        if ($Payment) {
            return;
        }

        $Payment = new Payment();
        $Payment->setCharge(0);
        $Payment->setSortNo($sortNo);
        $Payment->setVisible(true);
        $Payment->setMethod('Wechat');
        $Payment->setMethodClass(Wechat::class);

        $entityManager->persist($Payment);
        $entityManager->flush($Payment);
    }

    private function createPaidyPayment(ContainerInterface $container)
    {
        $entityManager = $container->get('doctrine')->getManager();
        $paymentRepository = $container->get(PaymentRepository::class);

        $Payment = $paymentRepository->findOneBy([], ['sort_no' => 'DESC']);
        $sortNo = $Payment ? $Payment->getSortNo() + 1 : 1;

        $Payment = $paymentRepository->findOneBy(['method_class' => Paidy::class]);
        if ($Payment) {
            return;
        }

        $Payment = new Payment();
        $Payment->setCharge(0);
        $Payment->setSortNo($sortNo);
        $Payment->setVisible(true);
        $Payment->setMethod('Paidy');
        $Payment->setMethodClass(Paidy::class);

        $entityManager->persist($Payment);
        $entityManager->flush($Payment);
    }

    private function createBankPayment(ContainerInterface $container)
    {
        $entityManager = $container->get('doctrine')->getManager();
        $paymentRepository = $container->get(PaymentRepository::class);

        $Payment = $paymentRepository->findOneBy([], ['sort_no' => 'DESC']);
        $sortNo = $Payment ? $Payment->getSortNo() + 1 : 1;

        $Payment = $paymentRepository->findOneBy(['method_class' => Bank::class]);
        if ($Payment) {
            return;
        }

        $Payment = new Payment();
        $Payment->setCharge(0);
        $Payment->setSortNo($sortNo);
        $Payment->setVisible(true);
        $Payment->setMethod('オート銀振');
        $Payment->setMethodClass(Bank::class);

        $entityManager->persist($Payment);
        $entityManager->flush($Payment);
    }

    private function createCvsPayment(ContainerInterface $container)
    {
        $entityManager = $container->get('doctrine')->getManager();
        $paymentRepository = $container->get(PaymentRepository::class);

        $Payment = $paymentRepository->findOneBy([], ['sort_no' => 'DESC']);
        $sortNo = $Payment ? $Payment->getSortNo() + 1 : 1;

        $Payment = $paymentRepository->findOneBy(['method_class' => Cvs::class]);
        if ($Payment) {
            return;
        }

        $Payment = new Payment();
        $Payment->setCharge(0);
        $Payment->setSortNo($sortNo);
        $Payment->setVisible(true);
        $Payment->setMethod('コンビニ決済');
        $Payment->setMethodClass(Convenience::class);

        $entityManager->persist($Payment);
        $entityManager->flush($Payment);
    }

    private function createConfig(ContainerInterface $container)
    {
        $entityManager = $container->get('doctrine.orm.entity_manager');
        $Config = $entityManager->find(Config::class, 1);
        if ($Config) {
            return;
        }

        $Config = new Config();
        $Config->setApiId('shop-id');
        $Config->setApiPassword('api-password');
        $Config->setApiUrl('https://gw.ccps.jp/payment.aspx');
        $Config->setCreditJob('0');
        $Config->setUseCvv('0');
        $Config->setUseSplit('0');

        $entityManager->persist($Config);
        $entityManager->flush($Config);
    }

    private function createMasterData(ContainerInterface $container, array $statuses, $class)
    {
        $entityManager = $container->get('doctrine')->getManager();
        $i = 0;
        foreach ($statuses as $id => $name) {
            $PaymentStatus = $entityManager->find($class, $id);
            if (!$PaymentStatus) {
                $PaymentStatus = new $class;
            }
            $PaymentStatus->setId($id);
            $PaymentStatus->setName($name);
            $PaymentStatus->setSortNo($i++);
            $entityManager->persist($PaymentStatus);
            $entityManager->flush($PaymentStatus);
        }
    }

    private function createPaymentStatuses(ContainerInterface $container)
    {
        $statuses = [
            PaymentStatus::OUTSTANDING => '未決済',
            PaymentStatus::ENABLED => '有効性チェック済',
            PaymentStatus::PROVISIONAL_SALES => '仮売上',
            PaymentStatus::ACTUAL_SALES => '実売上',
            PaymentStatus::CANCEL => 'キャンセル',
            PaymentStatus::REQUEST => '申込',
            PaymentStatus::SALES => '売上',
            PaymentStatus::EBPRERENTAL => '仮貸出',
            PaymentStatus::EBRENTAL => '貸出',
            PaymentStatus::EBTRANSFER => '入金',
        ];
        $this->createMasterData($container, $statuses, PaymentStatus::class);
    }

    private function createCvsPaymentStatuses(ContainerInterface $container)
    {
        $statuses = [
            CvsPaymentStatus::OUTSTANDING => '未決済',
            CvsPaymentStatus::REQUEST => '要求成功',
            CvsPaymentStatus::COMPLETE => '決済完了',
            CvsPaymentStatus::FAILURE => '決済失敗',
            CvsPaymentStatus::EXPIRED => '期限切れ',
        ];
        $this->createMasterData($container, $statuses, CvsPaymentStatus::class);
    }

    private function createCvsTypes(ContainerInterface $container)
    {
        $statuses = [
            CvsType::LAWSON => 'ローソン',
            CvsType::MINISTOP => 'ミニストップ',
            CvsType::SEVENELEVEN => 'セブンイレブン',
        ];
        $this->createMasterData($container, $statuses, CvsType::class);
    }
}
