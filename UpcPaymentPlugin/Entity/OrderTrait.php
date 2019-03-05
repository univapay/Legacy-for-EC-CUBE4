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

namespace Plugin\UpcPaymentPlugin\Entity;

use Doctrine\ORM\Mapping as ORM;
use Eccube\Annotation\EntityExtension;

/**
 * @EntityExtension("Eccube\Entity\Order")
 */
trait OrderTrait
{
    /**
     * トークンを保持するカラム.
     *
     * dtb_order.upc_payment_token
     *
     * @var string
     * @ORM\Column(type="string", nullable=true)
     */
    private $upc_payment_token;

    /**
     * クレジットカード番号の末尾4桁.
     * 永続化は行わず, 注文確認画面で表示する.
     *
     * @var string
     */
    private $upc_payment_card_no_last4;

    /**
     * 決済番号を保持するカラム
     *
     * dtb_order.upc_payment_plugin_pid
     * @var string
     * @ORM\Column(type="string", nullable=true)
     */
    private $upc_payment_plugin_pid;

    /**
     * コンビニ用種別を保持するカラム.
     *
     * dtb_order.upc_payment_cvs_type_id
     *
     * @var CvsType
     * @ORM\ManyToOne(targetEntity="Plugin\UpcPaymentPlugin\Entity\CvsType")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="upc_payment_cvs_type_id", referencedColumnName="id")
     * })
     */
    private $UpcPaymentPluginCvsType;


    /**
     * 決済ステータスを保持するカラム.
     *
     * dtb_order.upc_payment_payment_status_id
     *
     * @var UpcPaymentPluginPaymentStatus
     * @ORM\ManyToOne(targetEntity="Plugin\UpcPaymentPlugin\Entity\PaymentStatus")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="upc_payment_payment_status_id", referencedColumnName="id")
     * })
     */
    private $UpcPaymentPluginPaymentStatus;

    /**
     * コンビニ用決済ステータスを保持するカラム.
     *
     * dtb_order.upc_payment_payment_status_id
     *
     * @var UpcPaymentPluginCvsPaymentStatus
     * @ORM\ManyToOne(targetEntity="Plugin\UpcPaymentPlugin\Entity\CvsPaymentStatus")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="upc_payment_cvs_payment_status_id", referencedColumnName="id")
     * })
     */
    private $UpcPaymentPluginCvsPaymentStatus;

    /**
     * @return string
     */
    public function getUpcPaymentPluginToken()
    {
        return $this->upc_payment_token;
    }

    /**
     * @param string $upc_payment_token
     *
     * @return $this
     */
    public function setUpcPaymentPluginToken($upc_payment_token)
    {
        $this->upc_payment_token = $upc_payment_token;

        return $this;
    }

    /**
     * @return string
     */
    public function getUpcPaymentPluginCardNoLast4()
    {
        return $this->upc_payment_card_no_last4;
    }

    /**
     * @param string $upc_payment_card_no_last4
     */
    public function setUpcPaymentPluginCardNoLast4($upc_payment_card_no_last4)
    {
        $this->upc_payment_card_no_last4 = $upc_payment_card_no_last4;
    }

    /**
     * @return string
     */
    public function getUpcPaymentPluginPid()
    {
        return $this->upc_payment_plugin_pid;
    }

    /**
     * @param string $upc_payment_plugin_pid
     */
    public function setUpcPaymentPluginPid($upc_payment_plugin_pid)
    {
        $this->upc_payment_plugin_pid = $upc_payment_plugin_pid;
    }


    /**
     * @return CvsType
     */
    public function getUpcPaymentPluginCvsType()
    {
        return $this->UpcPaymentPluginCvsType;
    }

    /**
     * @param CvsType $UpcPaymentPluginCvsType
     */
    public function setUpcPaymentPluginCvsType(CvsType $UpcPaymentPluginCvsType)
    {
        $this->UpcPaymentPluginCvsType = $UpcPaymentPluginCvsType;
    }

    /**
     * @return PaymentStatus
     */
    public function getUpcPaymentPluginPaymentStatus()
    {
        return $this->UpcPaymentPluginPaymentStatus;
    }

    /**
     * @param PaymentStatus $UpcPaymentPluginPaymentStatus|null
     */
    public function setUpcPaymentPluginPaymentStatus(PaymentStatus $UpcPaymentPluginPaymentStatus = null)
    {
        $this->UpcPaymentPluginPaymentStatus = $UpcPaymentPluginPaymentStatus;
    }

    /**
     * @return CvsPaymentStatus
     */
    public function getUpcPaymentPluginCvsPaymentStatus()
    {
        return $this->UpcPaymentPluginCvsPaymentStatus;
    }

    /**
     * @param CvsPaymentStatus $UpcPaymentPluginCvsPaymentStatus|null
     */
    public function setUpcPaymentPluginCvsPaymentStatus(CvsPaymentStatus $UpcPaymentPluginCvsPaymentStatus = null)
    {
        $this->UpcPaymentPluginCvsPaymentStatus = $UpcPaymentPluginCvsPaymentStatus;
    }
}
