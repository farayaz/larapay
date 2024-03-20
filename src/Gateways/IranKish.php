<?php

namespace Farayaz\Larapay\Gateways;

use Farayaz\Larapay\Exceptions\LarapayException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use Illuminate\Support\Facades\View;

final class IranKish extends GatewayAbstract
{
    protected $url = 'https://ikc.shaparak.ir/';

    protected $statuses = [
        3 => 'از انجام تراکنش صرف نظر شد',
        5 => 'پذیرنده فروشگاهی نامعتبر است',
        64 => 'مبلغ تراکنش نادرست است،جمع مبالغ تقسیم وجوه برابر مبلغ کل تراکنش نمی باشد',
        94 => 'تراکنش تکراری است',
        25 => 'تراکنش اصلی یافت نشد',
        77 => 'روز مالی تراکنش نا معتبر است',
        63 => 'کد اعتبار سنجی پیام نا معتبر است',
        97 => 'کد تولید کد اعتبار سنجی نا معتبر است',
        30 => 'فرمت پیام نادرست است',
        86 => 'شتاب در حال Sign Off  است',
        55 => 'رمز کارت نادرست است',
        40 => 'عمل درخواستی پشتیبانی نمی شود',
        57 => 'انجام تراکنش مورد درخواست توسط پایانه انجام دهنده مجاز نمی باشد',
        58 => 'انجام تراکنش مورد درخواست توسط پایانه انجام دهنده مجاز نمی باشد',
        63 => 'تمهیدات امنیتی نقض گردیده است',
        96 => 'قوانین سامانه نقض گردیده است ، خطای داخلی سامانه',
        2 => 'تراکنش قبلا برگشت شده است',
        54 => 'تاریخ انقضا کارت سررسید شده است',
        62 => 'کارت محدود شده است',
        75 => 'تعداد دفعات ورود رمز اشتباه از حد مجاز فراتر رفته است',
        14 => 'اطلاعات کارت صحیح نمی باشد',
        51 => 'موجودی حساب کافی نمی باشد',
        56 => 'اطلاعات کارت یافت نشد',
        61 => 'مبلغ تراکنش بیش از حد مجاز است',
        65 => 'تعداد دفعات انجام تراکنش بیش از حد مجاز است',
        78 => 'کارت فعال نیست',
        79 => 'حساب متصل به کارت بسته یا دارای اشکال است',
        42 => 'کارت یا حساب مبدا (مقصد) در وضعیت پذیرش نمی باشد',
        31 => 'عدم تطابق کد ملی خریدار با دارنده کارت',
        98 => 'سقف استفاده از رمز دوم ایستا به پایان رسیده است',
        901 => 'درخواست نا معتبر است (Tokenization)',
        902 => 'پارامترهای اضافی درخواست نامعتبر می باشد (Tokenization)',
        903 => 'شناسه پرداخت نامعتبر می باشد (Tokenization)',
        904 => 'اطلاعات مرتبط با قبض نا معتبر می باشد (Tokenization)',
        905 => 'شناسه درخواست نامعتبر می باشد (Tokenization)',
        906 => 'درخواست تاریخ گذشته است (Tokenization)',
        907 => 'آدرس بازگشت نتیجه پرداخت نامعتبر می باشد (Tokenization',
        909 => 'پذیرنده نامعتبر می باشد(Tokenization)',
        910 => 'پارامترهای مورد انتظار پرداخت تسهیمی تامین نگردیده است(Tokenization)',
        911 => 'پارامترهای مورد انتظار پرداخت تسهیمی نا معتبر یا دارای اشکال می باشد(Tokenization)',
        912 => 'تراکنش درخواستی برای پذیرنده فعال نیست (Tokenization)',
        913 => 'تراکنش تسهیم برای پذیرنده فعال نیست (Tokenization)',
        914 => 'آدرس آی پی دریافتی درخواست نا معتبر می باشد',
        915 => 'شماره پایانه نامعتبر می باشد (Tokenization)',
        916 => 'شماره پذیرنده نا معتبر می باشد (Tokenization)',
        917 => 'نوع تراکنش اعلام شده در خواست نا معتبر می باشد (Tokenization)',
        918 => 'پذیرنده فعال نیست(Tokenization)',
        919 => 'مبالغ تسهیمی ارائه شده با توجه به قوانین حاکم بر وضعیت تسهیم پذیرنده ، نا معتبر است (Tokenization)',
        920 => 'شناسه نشانه نامعتبر می باشد',
        921 => 'شناسه نشانه نامعتبر و یا منقضی شده است',
        922 => 'نقض امنیت درخواست (Tokenization)',
        923 => 'ارسال شناسه پرداخت در تراکنش قبض مجاز نیست(Tokenization)',
        928 => 'مبلغ مبادله شده نا معتبر می باشد(Tokenization)',
        929 => 'شناسه پرداخت ارائه شده با توجه به الگوریتم متناظر نا معتبر می باشد(Tokenization)',
        930 => 'کد ملی ارائه شده نا معتبر می باشد(Tokenization)',

        'token-mismatch' => 'مغایرت توکن بازگشتی',
        'amount-mismatch' => 'مغایرت مبلغ پرداختی',
    ];

    protected $requirements = [
        'terminalId',
        'password',
        'acceptorId',
        'pubKey',
    ];

    public function request(
        int $id,
        int $amount,
        string $callbackUrl,
        string $nationalId,
        string $mobile
    ): array {
        $url = $this->url . 'api/v3/tokenization/make';
        $encrypted = $this->_encrypt(
            $this->config['pubKey'],
            $this->config['terminalId'],
            $this->config['password'],
            $amount
        );
        $params = [
            'request' => [
                'acceptorId' => $this->config['acceptorId'],
                'amount' => $amount,
                'billInfo' => null,
                'paymentId' => $id,
                'requestId' => $id,
                'requestTimestamp' => time(),
                'revertUri' => $callbackUrl,
                'terminalId' => $this->config['terminalId'],
                'transactionType' => 'Purchase',
            ],
            'authenticationEnvelope' => $encrypted,
        ];
        $result = $this->_request($url, $params);

        if ($result['responseCode'] != '00') {
            throw new LarapayException($result['description']);
        }

        return [
            'token' => $result['result']['token'],
            'fee' => $this->fee($amount),
        ];
    }

    public function redirect(int $id, string $token)
    {
        $action = $this->url . 'iuiv3/IPG/Index/';
        $fields = [
            'tokenIdentity' => $token,
        ];

        return View::make('larapay::redirector', compact('action', 'fields'));
    }

    public function verify(
        int $id,
        int $amount,
        string $token,
        array $params = []
    ): array {
        $default = [
            'responseCode' => null,
            'retrievalReferenceNumber' => null,
            'systemTraceAuditNumber' => null,
            'token' => null,
        ];
        $params = array_merge($default, $params);

        if ($params['responseCode'] != '00') {
            throw new LarapayException($this->translateStatus($params['responseCode']));
        }

        $url = $this->url . 'api/v3/confirmation/purchase';
        $data = [
            'terminalId' => $this->config['terminalId'],
            'retrievalReferenceNumber' => $params['retrievalReferenceNumber'],
            'systemTraceAuditNumber' => $params['systemTraceAuditNumber'],
            'tokenIdentity' => $params['token'],
        ];
        $result = $this->_request($url, $data);

        if ($result['result']['responseCode'] != '00') {
            throw new LarapayException($this->translateStatus($params['result']['responseCode']));
        }

        return [
            //TODO
            // 'card'           => null,
            'tracking_code' => $result['result']['systemTraceAuditNumber'],
            'reference_id' => $result['result']['retrievalReferenceNumber'],
            'result' => $result['result']['responseCode'],
            'fee' => $this->fee($amount),
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
            throw new LarapayException($e->getMessage());
        }
    }

    private function _encrypt(string $pubKey, string $terminalId, string $password, int $amount): array
    {
        $data = $terminalId . $password . str_pad((string) $amount, 12, '0', STR_PAD_LEFT) . '00';
        $data = hex2bin($data);
        $aesSecretKey = openssl_random_pseudo_bytes(16);
        $ivlen = openssl_cipher_iv_length($cipher = 'AES-128-CBC');
        $iv = openssl_random_pseudo_bytes($ivlen);
        $ciphertext_raw = openssl_encrypt($data, $cipher, $aesSecretKey, OPENSSL_RAW_DATA, $iv);
        $hmac = hash('sha256', $ciphertext_raw, true);
        $crypttext = '';

        openssl_public_encrypt($aesSecretKey . $hmac, $crypttext, $pubKey);

        return [
            'data' => bin2hex($crypttext),
            'iv' => bin2hex($iv),
        ];
    }
}
