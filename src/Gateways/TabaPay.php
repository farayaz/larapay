<?php

namespace Farayaz\Larapay\Gateways;

use Farayaz\Larapay\Exceptions\LarapayException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redirect;

class TabaPay extends GatewayAbstract
{
    protected $url = 'https://api.tabapay.ir/v1/';

    protected $statuses = [
        '-1' => 'خطای سیستمی',
        '-9' => 'ارسال پارامتر نامعتبر',
        '-10' => 'کد مرچنت اجباری است',
        '-11' => 'کد مرچنت نامعتبر است',
        '-12' => 'مبلغ اجباری است',
        '-13' => 'حداقل مبلغ : 10000 ریال',
        '-14' => 'حداکثر مبلغ : 2000000000 ریال',
        '-15' => 'لینک بازگشت اجباری است',
        '-16' => 'لینک بازگشت نامعتبر است',
        '-17' => 'شماره موبایل نامعتبر است',
        '-18' => 'ایمیل نامعتبر است',
        '-19' => 'پارامتر ورودی برای کد ملی نامعتبر است',
        '-20' => 'پارامتر ورودی برای شماره کارت بانکی نامعتبر است',
        '-21' => 'حداکثر طول مجاز توضیحات 300 کاراکتر است',
        '-22' => 'فرمت پارامتر دیتا اختیاری باید Json باشد',
        '-23' => 'حداکثر طول مجاز برای دیتا اختیاری 500 کاراکتر است',
        '-24' => 'پارامتر ورودی ارسال پیامک نامعتبر است',
        '-25' => 'نام نامعتبر است',
        '-26' => 'در صورتی که ارسال پیامک فعال باشد، پارامتر شماره موبایل نمی تواند خالی باشد',
        '-27' => 'دامنه فعلی با دامنه تایید شده تطابق ندارد',
        '-28' => 'پذیرنده درگاه غیر فعال شده است',
        '-29' => 'درگاه پرداخت غیر فعال شده است',
        '-30' => 'درگاه پرداخت پیدا نشد',
        '-31' => 'تراکنش نامعتبر',
        '-102' => 'تراکنش منقضی شده است',
        '-101' => 'تراکنش ناموفق',
        '-100' => 'شماره کارت پرداختی با شماره کارت درخواستی تطابق ندارد',
    ];

    protected $requirements = ['token'];

    public function request(
        int $id,
        int $amount,
        string $nationalId,
        string $mobile,
        string $callbackUrl,
        array $allowedCards = []
    ): array {
        $params = [
            'amount' => $amount,
            'callbackURL' => $callbackUrl,
            'mobile' => $mobile,
            'cardNumber' => ! empty($allowedCards) ? current($allowedCards) : null,
            'nationalCode' => $nationalId,
            'description' => $id,
        ];
        $result = $this->_request('post', $this->url . 'create', $params);

        return [
            'token' => $result['token'],
            'fee' => 0,
        ];
    }

    public function redirect(int $id, string $token, string $callbackUrl)
    {
        return Redirect::to('https://tabapay.ir/pay/' . $token);
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
            'responseCode' => null,
            'status' => null,
            'token' => null,
            'amount' => null,
        ];
        $params = array_merge($default, $params);
        if ($params['status'] != 'success') {
            throw new LarapayException($this->translateStatus($params['responseCode']));
        }
        if ($params['token'] != $token) {
            throw new LarapayException($this->translateStatus('token-mismatch'));
        }
        if ($params['amount'] != $amount) {
            throw new LarapayException($this->translateStatus('amount-mismatch'));
        }

        $data = [
            'token' => $token,
            'amount' => $amount,
        ];
        $result = $this->_request('post', $this->url . 'verify', $data);
        if ($result['status'] != 'success') {
            throw new LarapayException($this->translateStatus($result['responseCode']));
        }

        return [
            'fee' => 0,
            'card' => $result['cardNumber'],
            'result' => $result['status'],
            'reference_id' => $result['shaparakRefNumber'],
            'tracking_code' => $result['trackingCode'],
        ];
    }

    private function _request(string $method, string $url, array $data = [], array $headers = [], $timeout = 10)
    {
        $headers['Authorization'] = 'Bearer ' . $this->config['token'];
        try {
            $result = Http::timeout($timeout)
                ->withHeaders($headers)
                ->$method($url, $data)
                ->throw()
                ->json();

            return $result;
        } catch (RequestException $e) {
            $result = $e->response ? $e->response->json() : [];
            $message = $this->translateStatus($result['responseCode'] ?? $result['message'] ?? $e->getMessage());
            throw new LarapayException($message);
        } catch (ConnectionException $e) {
            throw new LarapayException($this->translateStatus('connection-exception'));
        }
    }
}
