<?php

namespace Farayaz\Larapay\Gateways;

use Farayaz\Larapay\Exceptions\LarapayException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use Illuminate\Support\Facades\Redirect;

final class PayPing extends GatewayAbstract
{
    protected $url = 'https://api.payping.ir/v2/';

    protected $statuses = [
        'http-400' => '400 - مشکلی در ارسال درخواست وجود دارد',
        'http-500' => '500 - مشکلی در سرور رخ داده است',
        'http-503' => '503 - سرور در حال حاضر قادر به پاسخگویی نمی‌باشد',
        'http-401' => '401 - عدم دسترسی',
        'http-403' => '403 - دسترسی غیر مجاز',
        'http-404' => '404 - آیتم درخواستی مورد نظر موجود نمی‌باشد',
        'id-mismatch' => 'عدم تطبیق شناسه برگشتی',
        'token-mismatch' => 'عدم تطبیق توکن برگشتی',
    ];

    protected $requirements = [
        'token',
    ];

    public function request(
        int $id,
        int $amount,
        string $nationalId,
        string $mobile,
        string $callbackUrl
    ): array {
        $url = $this->url . 'pay';
        $data = [
            'amount' => $amount / 10,
            'payerIdentity' => null,
            'payerName' => null,
            'description' => $id,
            'returnUrl' => $callbackUrl,
            'clientRefId' => $id,
        ];
        $result = $this->_request($url, $data);

        return [
            'token' => $result['code'],
            'fee' => 0,
        ];
    }

    public function redirect($id, $token)
    {
        return Redirect::to($this->url . 'pay/gotoipg/' . $token);
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
            'code' => null,
            'refid' => null,
            'clientrefid' => null,
            'cardnumber' => null,
            'cardhashpan' => null,
        ];
        $params = array_merge($default, $params);

        if ($params['code'] != $token) {
            throw new LarapayException($this->translateStatus('token-mismatch'));
        }

        if ($params['clientrefid'] != $id) {
            throw new LarapayException($this->translateStatus('id-mismatch'));
        }

        $url = $this->url . 'pay/verify';
        $data = [
            'amount' => $amount / 10,
            'refId' => $params['refid'],
        ];
        $result = $this->_request($url, $data);

        return [
            'result' => 'ok',
            'card' => $result['cardNumber'],
            'tracking_code' => $params['refid'],
            'reference_id' => $params['refid'],
            'fee' => 0,
        ];
    }

    private function _request($url, $data, $headers = [])
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
                        'Authorization' => 'Bearer ' . $this->config['token'],
                    ],
                    'json' => $data,
                    'timeout' => 10,
                ]
            );

            return json_decode($response->getBody(), true);
        } catch (BadResponseException $e) {
            $message = $e->getMessage();
            if ($e->hasResponse()) {
                $response = $e->getResponse();
                $message = $this->translateStatus('http-' . $response->getStatusCode());
                if ($response->getStatusCode() == 400) {
                    $result = json_decode($response->getBody(), 1);
                    $message = implode(', ', array_values($result));
                }
            }
            throw new LarapayException($message);
        }
    }
}
