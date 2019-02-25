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

namespace Plugin\UpcPayment\Entity;

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
     * コンビニ用種別を保持するカラム.
     *
     * dtb_order.upc_payment_cvs_type_id
     *
     * @var CvsType
     * @ORM\ManyToOne(targetEntity="Plugin\UpcPayment\Entity\CvsType")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="upc_payment_cvs_type_id", referencedColumnName="id")
     * })
     */
    private $UpcPaymentCvsType;


    /**
     * 決済ステータスを保持するカラム.
     *
     * dtb_order.upc_payment_payment_status_id
     *
     * @var UpcPaymentPaymentStatus
     * @ORM\ManyToOne(targetEntity="Plugin\UpcPayment\Entity\PaymentStatus")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="upc_payment_payment_status_id", referencedColumnName="id")
     * })
     */
    private $UpcPaymentPaymentStatus;

    /**
     * コンビニ用決済ステータスを保持するカラム.
     *
     * dtb_order.upc_payment_payment_status_id
     *
     * @var UpcPaymentCvsPaymentStatus
     * @ORM\ManyToOne(targetEntity="Plugin\UpcPayment\Entity\CvsPaymentStatus")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="upc_payment_cvs_payment_status_id", referencedColumnName="id")
     * })
     */
    private $UpcPaymentCvsPaymentStatus;

    /**
     * @return string
     */
    public function getUpcPaymentToken()
    {
        return $this->upc_payment_token;
    }

    /**
     * @param string $upc_payment_token
     *
     * @return $this
     */
    public function setUpcPaymentToken($upc_payment_token)
    {
        $this->upc_payment_token = $upc_payment_token;

        return $this;
    }

    /**
     * @return string
     */
    public function getUpcPaymentCardNoLast4()
    {
        return $this->upc_payment_card_no_last4;
    }

    /**
     * @param string $upc_payment_card_no_last4
     */
    public function setUpcPaymentCardNoLast4($upc_payment_card_no_last4)
    {
        $this->upc_payment_card_no_last4 = $upc_payment_card_no_last4;
    }

    /**
     * @return CvsType
     */
    public function getUpcPaymentCvsType()
    {
        return $this->UpcPaymentCvsType;
    }

    /**
     * @param CvsType $UpcPaymentCvsType
     */
    public function setUpcPaymentCvsType(CvsType $UpcPaymentCvsType)
    {
        $this->UpcPaymentCvsType = $UpcPaymentCvsType;
    }

    /**
     * @return PaymentStatus
     */
    public function getUpcPaymentPaymentStatus()
    {
        return $this->UpcPaymentPaymentStatus;
    }

    /**
     * @param PaymentStatus $UpcPaymentPaymentStatus|null
     */
    public function setUpcPaymentPaymentStatus(PaymentStatus $UpcPaymentPaymentStatus = null)
    {
        $this->UpcPaymentPaymentStatus = $UpcPaymentPaymentStatus;
    }

    /**
     * @return CvsPaymentStatus
     */
    public function getUpcPaymentCvsPaymentStatus()
    {
        return $this->UpcPaymentCvsPaymentStatus;
    }

    /**
     * @param CvsPaymentStatus $UpcPaymentCvsPaymentStatus|null
     */
    public function setUpcPaymentCvsPaymentStatus(CvsPaymentStatus $UpcPaymentCvsPaymentStatus = null)
    {
        $this->UpcPaymentCvsPaymentStatus = $UpcPaymentCvsPaymentStatus;
    }
}
