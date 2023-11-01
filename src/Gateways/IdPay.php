<?php

namespace Farayaz\Larapay\Gateways;

use Farayaz\Larapay\Exceptions\GatewayException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;

final class IdPay extends GatewayAbstract
{
    protected $url = 'https://api.idpay.ir';

    protected $statuses = [
        '1' => 'پرداخت انجام نشده است.',
        "2" => "پرداخت ناموفق بوده است.",
        "3" => "خطا رخ داده است.",
        "4" => "بلوکه شده.",
        "5" => "برگشت به پرداخت کننده.",
        "6" => "برگشت خورده سیستمی.",
        "7" => "انصراف از پرداخت",
        "8" => "به درگاه پرداخت منتقل شد",
        "10" => "در انتظار تایید پرداخت.",
        "100" => "پرداخت تایید شده است.",
        "101" => "پرداخت قبلا تایید شده است.",
        "200" => "به دریافت کننده واریز شد.",
        "11" => "کاربر مسدود شده است.",
        "12" => "API Key یافت نشد.",
        "13" => "درخواست شما از ip ارسال شده است. این IP با IP های ثبت شده در وب‌سرویس همخوانی ندارد.",
        "14" => "وب‌سرویس تایید نشده است.",
        "15" => "سرویس مورد نظر در دسترس نمی‌باشد.",
        "21" => "حساب بانکی متصل به وب‌سرویس تایید نشده است.",
        "22" => "وب سریس یافت نشد.",
        "23" => "اعتبار سنجی وب‌سرویس ناموفق بود.",
        "24" => "حساب بانکی مرتبط با این وب‌سرویس غیر فعال شده است.",
        "31" => "کد تراکنش id نباید خالی باشد.",
        "32" => "شماره سفارش order_id نباید خالی باشد.",
        "33" => "مبلغ amount نباید خالی باشد.",
        "34" => "مبلغ amount باید بیشتر از min-amount ریال باشد.",
        "35" => "مبلغ amount باید کمتر از max-amount ریال باشد.",
        "36" => "مبلغ amount بیشتر از حد مجاز است.",
        "37" => "آدرس بازگشت callback نباید خالی باشد.",
        "38" => "درخواست شما از آدرس domain ارسال شده است. دامنه آدرس بازگشت callback با آدرس ثبت شده در وب‌سرویس همخوانی ندارد.",
        "39" => "آدرس بازگشت callback نامعتبر است.",
        "41" => "فیلتر وضعیت تراکنش ها می‌بایست آرایه ای (لیستی) از وضعیت‌های مجاز در مستندات باشد.",
        "42" => "فیلتر تاریخ پرداخت می‌بایست آرایه ای شامل المنت‌های min و max از نوع timestamp باشد.",
        "43" => "فیلتر تاریخ تسویه می‌بایست آرایه ای شامل المنت‌های min و max از نوع timestamp باشد.",
        "44" => "فیلتر تراکنش صحیح نمی باشد.",
        "51" => "تراکنش ایجاد نشد.",
        "52" => "استعلام نتیجه‌ای نداشت.",
        "53" => "تایید پرداخت امکان‌پذیر نیست.",
        "54" => "مدت زمان تایید پرداخت سپری شده است.",
        "empty-id" => 'شناسه تراکنش ایجاد نشد.'
    ];

    protected $requirements = ['apiKey'];

    public function request(int $id, int $amount, string $callback): array
    {
        $url = $this->url . '/v1.1/payment';

        $params = [
            'order_id' => $id,
            'amount' => $amount,
            'callback' => $callback,
            'name' => null,
            'phone' => null,
            'mail' => null,
            'desc' => null,
            'reseller' => null,
        ];

        $result = $this->_request($url, $params);

        if (empty($result['id'])) {
            throw new GatewayException($this->translateStatus('empty-id'));
        }

        return [
            'token' => $result['id'],
            'fee' => $this->fee($amount),
        ];
    }

    public function verify(
        int $id,
        int $amount,
        string $token,
        array $params = []
    ): array {
        $default = [
            'id' => null,
            'date' => null,
            'amount' => null,
            'status' => null,
            'card_no' => null,
            'track_id' => null,
            'order_id' => null,
            'hashed_card_no' => null,
        ];
        $params = array_merge($default, $params);

        if ($params['status'] != '100') {
            throw new GatewayException($this->translateStatus($params['status']));
        }

        $url = $this->url . '/v1.1/payment/verify';
        $data = [
            'id' => $params['id'],
            'order_id' => $params['order_id'],
        ];

        $result = $this->_request($url, $data);

        if ($result['status'] != '100') {
            throw new GatewayException($this->translateStatus($result['status']));
        }

        return [
            'fee' => $this->fee($amount),
            'result' => $result['status'],
            'reference_id' => $result['track_id'],
            'tracking_code' => $result['payment']['track_id'],
        ];
    }

    public function redirect(int $id, string $token)
    {
        $action = $this->url . '/p/ws/' . $token;
        $fields = [];

        return view('larapay::redirector', compact('action', 'fields'));
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
                        'X-API-KEY' => $this->config['apiKey'],
                        'X-SANDBOX' => 0, // set zero to use production gateway
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ],
                    'json' => $data,
                    'timeout' => 10,
                ]
            );

            return json_decode($response->getBody()->getContents(), true);
        } catch (BadResponseException $e) {
            throw new GatewayException($e->getMessage());
        }
    }
}
