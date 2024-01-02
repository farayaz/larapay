<?php

namespace Farayaz\Larapay\Gateways;

use Exception;
use Farayaz\Larapay\Exceptions\GatewayException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use Morilog\Jalali\Jalalian;

final class QMB extends GatewayAbstract
{
    protected $url = 'https://kalayeiranipg.qmb.ir/pg/';

    protected $statuses = [
        'id-mismatch' => 'عدم تطبیق شناسه بازگشتی',
        'token-mismatch' => 'عدم تطبیق توکن',

        '03' => 'طرح اقساطی پذیرنده با کارت منطبق نیست.',
        '06' => 'بروز خطای سیستمی',
        '12' => 'تراکنش نامعتبر است.',
        '25' => 'عدم وجود اطلاعات مورد نظر جهت به روز آوری یا انجام عملیات.',
        '31' => 'جدول آزادسازی پذیرنده تعریف نشده است.',
        '33' => 'تاریخ انقضا کارت سپری شده',
        '39' => 'کارت حساب اعتباری ندارد.',
        '41' => 'کارت مفقودی می باشد.',
        '51' => 'موجودی کافی نمی باشد.',
        '54' => 'تاریخ انقضا کارت سپری شده است.',
        '55' => 'رمز کارت وارد شده اشتباه می باشد.',
        '60' => 'پذیرنده غیرفعال می باشد.',
        '75' => 'تعداد دفعات ورود رمز بیش از حد مجاز است.',
        '84' => 'وضعیت سامانه یا بانک غیرفعال می باشد.',
        '96' => 'بروز خطای سیستمی در انجام تراکنش.',

        '9102' => 'مبلغ تراکنش بیشتر یا کمتر از حد مجاز می باشد.',
        '9105' => 'تقسیم وجه برای پذیرنده غیرفعال است.',
        '9201' => 'مشتری از پرداخت انصراف داده است.',
        '9214' => 'شماره تراکنش معتبر نمی باشد.',
        '9215' => 'چرخه تراکنش نقض شده است.',
        '9217' => 'تراکنش دارای مغایرت می باشد.',
        '9219' => 'زمان انجام تراکنش به پایان رسیده و لغو شده است.',
        '9220' => 'تراکنش قبلا لغو شده است.',
        '9221' => 'تراکنش قبلا با موفقیت انجام شده',
        '9222' => 'دسترسی هم زمان به تراکنش',
        '9223' => 'خطای غیر منتظره',
        '9224' => 'قبض قبلا پرداخت شده است',
        '9301' => 'درخواست با امضای دیجیتال مطابقت ندارد',
        '9302' => 'دسترسی غیر مجاز',
        '9501' => 'از شبکه پرداخت پاسخی دریافت نشد',
        '9601' => 'پارامترهای ورودی اشتباه می باشد',
        '9701' => 'ترمینال در سیستم وجود ندارد',
        '9702' => 'کد ملی پذیرنده در سیستم وجود ندارد',
        '9703' => 'کد ملی مشتری در سیستم وجود ندارد',
        '9704' => 'شماره موبایل مشتری در سیستم وجود ندارد',
        '9705' => 'ارسال رمز با خطا مواجه شد',
        '9706' => 'شماره کارت مشتری در سیستم وجود ندارد.',
    ];

    protected $requirements = [
        'terminal_id',
        'merchant_nid',
        'encrypt_key',
    ];

    public function request(int $id, int $amount, string $callback): array
    {
        $url = $this->url . 'service/vpos/trxReq';
        $params = [
            'terminal-id' => $this->config['terminal_id'],
            'merchant-nid' => $this->config['merchant_nid'],
            'order-id' => $id,
            'revert-url' => $callback,
            'trxtype' => 'sale',
            'amount' => $amount,
            'date' => Jalalian::now()->format('Y/m/d H:i'),
        ];
        $params['sign'] = $this->sign($params);

        try {
            $result = $this->_request($url, $params);
        } catch (Exception $e) {
            throw new GatewayException($e->getMessage());
        }

        if ($result['resp-code'] != '00') {
            throw new GatewayException($this->translateStatus($result['resp-code']));
        }

        return [
            'token' => $result['transaction-id'],
            'fee' => 0,
        ];
    }

    public function redirect($id, $token)
    {
        $action = $this->url . 'pay';
        $fields = [
            'transaction_id' => $token,
            'sign' => $this->sign([$token]),
        ];

        return view('larapay::redirector', compact('action', 'fields'));
    }

    public function verify(int $id, int $amount, string $token, array $params = []): array
    {
        $default = [
            'resp-code' => 'null',
            'transaction-id' => null,
            'trace' => null,
            'rrn' => null,
        ];
        $params = array_merge($default, $params);

        if ($params['resp-code'] != '00') {
            throw new GatewayException($this->translateStatus($params['resp-code']));
        }

        if ($params['transaction-id'] != $token) {
            throw new GatewayException($this->translateStatus('id-missmatch'));
        }

        $url = $this->config['url'] . 'service/vpos/trxConfirm';
        $data = [
            'transaction-id' => $token,
            'operation' => 'confirm',
        ];
        $data['sign'] = $this->sign($data);
        try {
            $result = $this->_request($url, $params);
        } catch (Exception $e) {
            throw new GatewayException($e->getMessage());
        }

        if ($result['resp-code'] != '00') {
            throw new GatewayException($this->translateStatus($result['resp-code']));
        }
        if ($result['transaction-id'] != $token) {
            throw new GatewayException($this->translateStatus('token-missmatch'));
        }

        return [
            'result' => $params['resp-code'],
            'card' => null,
            'tracking_code' => $params['trace'],
            'reference_id' => $params['rrn'],
            'fee' => 0,
        ];
    }

    private function _request(string $url, array $data)
    {
        $client = new Client;

        try {
            $response = $client->request(
                'POST',
                $url,
                [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ],
                    'json' => $data,
                    'timeout' => 10,
                ]
            );

            return json_decode($response->getBody(), true);
        } catch (BadResponseException $e) {
            throw new GatewayException($e->getMessage());
        }
    }

    private function sign(array $params)
    {
        return hash_hmac('sha256', implode('*', $params), hex2bin($this->config['encrypt_key']));
    }
}
