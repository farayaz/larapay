<?php

namespace Farayaz\Larapay\Gateways;

use Farayaz\Larapay\Exceptions\LarapayException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redirect;

class Payir extends GatewayAbstract
{
    protected $url = 'https://pay.ir/pg/';

    protected $statuses = [
        '0' => 'درحال حاضر درگاه بانکی قطع شده و مشکل بزودی برطرف می شود',
        '-1' => 'API Key ارسال نمی شود',
        '-2' => 'Token ارسال نمی شود',
        '-3' => 'API Key ارسال شده اشتباه است',
        '-4' => 'امکان انجام تراکنش برای این پذیرنده وجود ندارد',
        '-5' => 'تراکنش با خطا مواجه شده است',
        '-6' => 'تراکنش تکراریست یا قبلا انجام شده',
        '-7' => 'مقدار Token ارسالی اشتباه است',
        '-8' => 'شماره تراکنش ارسالی اشتباه است',
        '-9' => 'زمان مجاز برای انجام تراکنش تمام شده',
        '-10' => 'مبلغ تراکنش ارسال نمی شود',
        '-11' => 'مبلغ تراکنش باید به صورت عددی و با کاراکترهای لاتین باشد',
        '-12' => 'مبلغ تراکنش می بایست عددی بین 10,000 و 500,000,000 ریال باشد',
        '-13' => 'مقدار آدرس بازگشتی ارسال نمی شود',
        '-14' => 'آدرس بازگشتی ارسالی با آدرس درگاه ثبت شده در شبکه پرداخت پی یکسان نیست',
        '-15' => 'امکان وریفای وجود ندارد. این تراکنش پرداخت نشده است',
        '-16' => 'یک یا چند شماره موبایل از اطلاعات پذیرندگان ارسال شده اشتباه است',
        '-17' => 'میزان سهم ارسالی باید بصورت عددی و بین 1 تا 100 باشد',
        '-18' => 'فرمت پذیرندگان صحیح نمی باشد',
        '-19' => 'هر پذیرنده فقط یک سهم میتواند داشته باشد',
        '-20' => 'مجموع سهم پذیرنده ها باید 100 درصد باشد',
        '-21' => 'Reseller ID ارسالی اشتباه است',
        '-22' => 'فرمت یا طول مقادیر ارسالی به درگاه اشتباه است',
        '-23' => 'سوییچ PSP ( درگاه بانک ) قادر به پردازش درخواست نیست. لطفا لحظاتی بعد مجددا تلاش کنید',
        '-24' => 'شماره کارت باید بصورت 16 رقمی، لاتین و چسبیده بهم باشد',
        '-25' => 'امکان استفاده از سرویس در کشور مبدا شما وجود نداره',
        '-26' => 'امکان انجام تراکنش برای این درگاه وجود ندارد',
        '-27' => 'در انتظار تایید درگاه توسط شاپرک',
        '-28' => 'امکان تسهیم تراکنش برای این درگاه وجود ندارد',

        'token-mismatch' => 'عدم تطبیق توکن',
    ];

    protected $requirements = ['api'];

    public function request(
        int $id,
        int $amount,
        string $nationalId,
        string $mobile,
        string $callbackUrl
    ): array {
        $url = $this->url . 'send';
        $params = [
            'api' => $this->config['api'],
            'amount' => $amount,
            'redirect' => $callbackUrl,
            'mobile' => '',
            'factorNumber' => $id,
            'description' => $id,
            'validCardNumber' => '',
        ];

        $result = $this->_request('post', $url, $params);

        return [
            'token' => $result['token'],
            'fee' => $this->fee($amount),
        ];
    }

    public function redirect(int $id, string $token, string $callbackUrl)
    {
        return Redirect::to($this->url . $token);
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
            'status' => null,
            'token' => null,
        ];
        $params = array_merge($default, $params);

        if ($params['token'] != $token) {
            throw new LarapayException($this->translateStatus('token-mismatch'));
        }
        if ($params['status'] != 1) {
            throw new LarapayException($this->translateStatus($params['status']));
        }

        $url = $this->url . 'verify';
        $data = [
            'api' => $this->config['api'],
            'token' => $token,
        ];
        $result = $this->_request('post', $url, $data);

        return [
            'result' => $this->translateStatus($result['status']),
            'card' => $result['cardNumber'],
            'tracking_code' => $result['traceNumber'],
            'reference_id' => $result['traceNumber'],
            'fee' => $this->fee($amount),
        ];
    }

    private function _request(string $method, string $url, array $data = [], array $headers = [], $timeout = 10)
    {
        try {
            $result = Http::timeout($timeout)
                ->withHeaders($headers)
                ->$method($url, $data)
                ->throw()
                ->json();

            return $result;
        } catch (RequestException $e) {
            $message = $e->getMessage();
            if ($e->response->status() == 422) {
                $result = $e->response->json();
                $message = $result['errorCode'] . ' - ' . $result['errorMessage'];
            }
            throw new LarapayException($message);
        } catch (ConnectionException $e) {
            throw new LarapayException($this->translateStatus('connection-exception'));
        }
    }

    public function fee(int $amount): int
    {
        return min(20_000, round($amount * 0.01));
    }
}
