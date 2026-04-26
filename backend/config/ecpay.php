<?php

/**
 * ECPay 金流設定
 *
 * 憑證從 system_settings 表動態讀取（key: ecpay_environment, ecpay_sandbox_merchant_id, ...）
 * sandbox fallback 使用 ECPay 官方公開沙箱測試值。
 */
return [
    'urls' => [
        'sandbox' => [
            'aio'    => 'https://payment-stage.ecpay.com.tw/Cashier/AioCheckOut/V5',
            'query'  => 'https://payment-stage.ecpay.com.tw/Cashier/QueryTradeInfo/V5',
            'refund' => 'https://payment-stage.ecpay.com.tw/CreditDetail/DoAction',
        ],
        'production' => [
            'aio'    => 'https://payment.ecpay.com.tw/Cashier/AioCheckOut/V5',
            'query'  => 'https://payment.ecpay.com.tw/Cashier/QueryTradeInfo/V5',
            'refund' => 'https://payment.ecpay.com.tw/CreditDetail/DoAction',
        ],
    ],

    // ECPay 官方公開沙箱測試憑證（可直接使用，無需申請）
    // Ref: https://developers.ecpay.com.tw/
    'sandbox_fallback' => [
        'merchant_id' => '2000132',
        'hash_key'    => '5294y06JbISpM5x9',
        'hash_iv'     => 'v77hoKGq4kWxNNIS',
    ],
];
