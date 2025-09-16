<?php

namespace Farayaz\Larapay\Gateways;

use Farayaz\Larapay\Exceptions\LarapayException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\View;
use SoapClient;
use SoapFault;

class BehPardakht extends GatewayAbstract
{
    protected $statuses = [
        11 => 'شماره کارت نامعتبر است',
        12 => 'موجودی کافی نیست',
        13 => 'رمز نادرست است',
        14 => 'تعداد دفعات وارد کردن رمز بیش از حد مجاز است',
        15 => 'کارت نامعتبر است',
        16 => 'دفعات برداشت وجه بیش از حد مجاز است',
        17 => 'کاربر از انجام تراکنش منصرف شده است',
        18 => 'تاریخ انقضای کارت گذشته است',
        19 => 'مبلغ برداشت وجه بیش از حد مجاز است',
        111 => 'صادر کننده کارت نامعتبر است',
        112 => 'خطای سوییچ صادر کننده کارت',
        113 => 'پاسخی از صادر کننده کارت دریافت نشد',
        114 => 'دارنده این کارت مجاز به انجام این تراکنش نیست',
        21 => 'پذیرنده نامعتبر است',
        23 => 'خطای امنیتی رخ داده است',
        24 => 'اطلاعات کاربری پذیرنده نامعتبر است',
        25 => 'مبلغ نامعتبر است',
        31 => 'پاسخ نامعتبر است',
        32 => 'فرمت اطلاعات وارد شده صحیح نمی‌باشد',
        33 => 'حساب نامعتبر است',
        34 => 'خطای سیستمی',
        35 => 'تاریخ نامعتبر است',
        41 => 'شماره درخواست تکراری است',
        42 => 'تراکنش Sale یافت نشد',
        43 => 'قبلا درخواست Verfiy داده شده است',
        44 => 'درخواست Verfiy یافت نشد',
        45 => 'تراکنش Settle شده است',
        46 => 'تراکنش Settle نشده است',
        47 => 'تراکنش Settle یافت نشد',
        48 => 'تراکنش Reverse شده است',
        49 => 'تراکنش Refund یافت نشد.',
        412 => 'شناسه قبض نادرست است',
        413 => 'شناسه پرداخت نادرست است',
        414 => 'سازمان صادر کننده قبض نامعتبر است',
        415 => 'زمان جلسه کاری به پایان رسیده است',
        416 => 'خطا در ثبت اطلاعات',
        417 => 'شناسه پرداخت کننده نامعتبر است',
        418 => 'اشکال در تعریف اطلاعات مشتری',
        419 => 'تعداد دفعات ورود اطلاعات از حد مجاز گذشته است',
        421 => 'IP نامعتبر است',
        51 => 'تراکنش تکراری است',
        54 => 'تراکنش مرجع موجود نیست',
        55 => 'تراکنش نامعتبر است',
        61 => 'خطا در واریز',

        'token-mismatch' => 'عدم تطبیق توکن',
    ];

    protected $requirements = ['terminal_id', 'username', 'password', 'is_credit'];

    public function request(
        int $id,
        int $amount,
        string $nationalId,
        string $mobile,
        string $callbackUrl,
        array $allowedCards = []
    ): array {
        $method = $this->config['is_credit'] ? 'bpVirtualPayRequest' : 'bpPayRequest';
        $params = [
            'terminalId' => $this->config['terminal_id'],
            'userName' => $this->config['username'],
            'userPassword' => $this->config['password'],
            'orderId' => $id,
            'amount' => $amount,
            'localDate' => Date::now()->format('Ymd'),
            'localTime' => Date::now()->format('His'),
            'additionalData' => '',
            'callBackUrl' => $callbackUrl,
            'payerId' => 0,
        ];

        ini_set('default_socket_timeout', 10);
        try {
            $client = new SoapClient($this->_url('services/pgw?wsdl'));
            $response = $client->$method($params);
        } catch (SoapFault $e) {
            throw new LarapayException($e->getMessage());
        }

        $result = explode(',', $response->return);
        if ($result[0] != '0') {
            throw new LarapayException($this->translateStatus($result[0]));
        }

        return [
            'token' => $result[1],
            'fee' => $this->fee($amount),
        ];
    }

    public function redirect(int $id, string $token, string $callbackUrl)
    {
        $action = $this->_url('startpay.mellat');
        $fields = [
            'RefId' => $token,
        ];

        return View::make('larapay::redirector', compact('action', 'fields'));
    }

    public function verify(
        int $id,
        int $amount,
        string $nationalId,
        string $mobile,
        string $token,
        array $params
    ): array {
        $default = [
            'ResCode' => null,
            'RefId' => null,
            'CardHolderPan' => null,
            'SaleReferenceId' => null,
        ];
        $params = array_merge($default, $params);

        if ($params['RefId'] != $token) {
            throw new LarapayException('token-mismatch');
        }
        if ($params['ResCode'] != '0') {
            throw new LarapayException($this->translateStatus($params['ResCode']));
        }

        $data = [
            'terminalId' => $this->config['terminal_id'],
            'userName' => $this->config['username'],
            'userPassword' => $this->config['password'],
            'orderId' => $id,
            'saleOrderId' => $id,
            'saleReferenceId' => $params['SaleReferenceId'],
        ];
        ini_set('default_socket_timeout', 10);
        try {
            $client = new SoapClient($this->_url('services/pgw?wsdl'));
            $response = $client->bpVerifyRequest($data);
        } catch (SoapFault $e) {
            throw new LarapayException($e->getMessage());
        }

        $data = [
            'terminalId' => $this->config['terminal_id'],
            'userName' => $this->config['username'],
            'userPassword' => $this->config['password'],
            'orderId' => $id,
            'saleOrderId' => $id,
            'saleReferenceId' => $params['SaleReferenceId'],
        ];
        try {
            $response = $client->bpSettleRequest($data);
        } catch (SoapFault $e) {
            throw new LarapayException($e->getMessage());
        }

        if ($response->return == '0' || $response->return == '45') {
            return [
                'card' => $params['CardHolderPan'],
                'tracking_code' => $params['SaleReferenceId'],
                'reference_id' => $params['SaleReferenceId'],
                'result' => $params['ResCode'],
                'fee' => $this->fee($amount),
            ];
        }
        throw new LarapayException($this->translateStatus($response->return));
    }

    protected function _url($path)
    {
        $channel = $this->config['is_credit'] ? 'pgwCreditchannel' : 'pgwchannel';

        return 'https://bpm.shaparak.ir/' . $channel . '/' . $path;
    }
}
