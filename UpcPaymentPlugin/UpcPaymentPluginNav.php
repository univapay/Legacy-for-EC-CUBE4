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

use Eccube\Common\EccubeNav;

class UpcPaymentPluginNav implements EccubeNav
{
    /**
     * @return array
     */
    public static function getNav()
    {
        return [
            'order' => [
                'children' => [
                    'upc_payment_plugin_admin_payment_status' => [
                        'name' => 'upc_payment.admin.nav.payment_list',
                        'url' => 'upc_payment_plugin_admin_payment_status',
                    ],
                ],
            ],
        ];
    }
}
