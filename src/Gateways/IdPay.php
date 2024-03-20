<?php

namespace Farayaz\Larapay\Gateways;

use Farayaz\Larapay\Exceptions\LarapayException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use Illuminate\Support\Facades\Redirect;

final class IdPay extends GatewayAbstract
{
    protected $url = 'https://api.idpay.ir';

    protected $statuses = [
        '1' => 'پرداخت انجام نشده است.',
        '2' => 'پرداخت ناموفق بوده است.',
        '3' => 'خطا رخ داده است.',
        '4' => 'بلوکه شده.',
        '5' => 'برگشت به پرداخت کننده.',
        '6' => 'برگشت خورده سیستمی.',
        '7' => 'انصراف از پرداخت',
        '8' => 'به درگاه پرداخت منتقل شد',
        '10' => 'در انتظار تایید پرداخت.',
        '100' => 'پرداخت تایید شده است.',
        '101' => 'پرداخت قبلا تایید شده است.',
        '200' => 'به دریافت کننده واریز شد.',

        'token-mismatch' => 'عدم تطبیق توکن بازگشتی',
    ];

    protected $requirements = ['apiKey', 'sandbox'];

    public function request(
        int $id,
        int $amount,
        string $nationalId,
        string $mobile,
        string $callbackUrl
    ): array {
        $url = $this->url . '/v1.1/payment';

        $params = [
            'order_id' => $id,
            'amount' => $amount,
            'callback' => $callbackUrl,
            'name' => null,
            'phone' => null,
            'mail' => null,
            'desc' => null,
            'reseller' => null,
        ];

        $result = $this->_request($url, $params);

        return [
            'token' => $result['id'],
            'fee' => $this->fee($amount),
        ];
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
        if ($params['status'] != '10') {
            throw new LarapayException($this->translateStatus($params['status']));
        }
        if ($params['id'] != $token) {
            throw new LarapayException($this->translateStatus('token-mismatch'));
        }

        $url = $this->url . '/v1.1/payment/verify';
        $data = [
            'id' => $token,
            'order_id' => $id,
        ];
        $result = $this->_request($url, $data);
        if ($result['status'] != '100') {
            throw new LarapayException($this->translateStatus($result['status']));
        }

        return [
            'fee' => $this->fee($amount),
            'card' => $result['payment']['card_no'],
            'result' => $result['status'],
            'reference_id' => $result['track_id'],
            'tracking_code' => $result['payment']['track_id'],
        ];
    }

    public function redirect(int $id, string $token, string $callbackUrl)
    {
        $url = 'https://idpay.ir/p/ws' . ($this->config['sandbox'] ? '-sandbox' : '') . '/' . $token;

        return Redirect::to($url);
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
                        'X-SANDBOX' => $this->config['sandbox'],
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ],
                    'json' => $data,
                    'timeout' => 10,
                ]
            );

            return json_decode($response->getBody()->getContents(), true);
        } catch (BadResponseException $e) {
            $message = $e->getMessage();
            if ($e->hasResponse()) {
                $result = json_decode($e->getResponse()->getBody(), 1);
                $message = $this->translateStatus($result['error_message'] ?? 'unknown error');
            }
            throw new LarapayException($message);
        }
    }
}
