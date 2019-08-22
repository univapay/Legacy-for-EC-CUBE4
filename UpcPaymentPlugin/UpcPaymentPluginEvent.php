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

use Eccube\Event\TemplateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Plugin\UpcPaymentPlugin\Repository\ConfigRepository;

class UpcPaymentPluginEvent implements EventSubscriberInterface
{
    private $configRepository; // クラス変数を定義

    public function __construct(ConfigRepository $configRepository)
    {
        $this->configRepository = $configRepository; // クラス変数にセット
    }
    /**
     * リッスンしたいサブスクライバのイベント名の配列を返します。
     * 配列のキーはイベント名、値は以下のどれかをしてします。
     * - 呼び出すメソッド名
     * - 呼び出すメソッド名と優先度の配列
     * - 呼び出すメソッド名と優先度の配列の配列
     * 優先度を省略した場合は0
     *
     * 例：
     * - array('eventName' => 'methodName')
     * - array('eventName' => array('methodName', $priority))
     * - array('eventName' => array(array('methodName1', $priority), array('methodName2')))
     *
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            '@admin/Order/edit.twig' => 'onAdminOrderEditTwig',
            'Shopping/index.twig' => 'onShoppingIndexTwig',
        ];
    }

    public function onAdminOrderEditTwig(TemplateEvent $event)
    {
        $event->addSnippet('@UpcPaymentPlugin/admin/order_edit.twig');
    }


    /**
     * @param TemplateEvent $event
     */
    public function onShoppingIndexTwig(TemplateEvent $event)
    {
        $BaseInfo = $this->configRepository->find(1);
        $event->setParameter('Config', $BaseInfo); // パラメータに値をセット
    }

}
