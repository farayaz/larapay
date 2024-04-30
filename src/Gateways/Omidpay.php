<?php

namespace Farayaz\Larapay\Gateways;

use Farayaz\Larapay\Exceptions\LarapayException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use Illuminate\Support\Facades\View;

final class Omidpay extends GatewayAbstract
{
    protected $url = 'https://ref.sayancard.ir/ref-payment/RestServices/mts/';

    protected $statuses = [
        'erAAS_InvalidUseridOrPass' => 'نام کاربری یا رمز عبور نامعتبر',

        'token-mismatch' => 'عدم تطبیق توکن بازگشتی',
    ];

    protected $requirements = ['user_id', 'password'];

    public function request(
        int $id,
        int $amount,
        string $nationalId,
        string $mobile,
        string $callbackUrl
    ): array {
        $url = $this->url . 'generateTokenWithNoSign/';
        $params = [
            'WSContext' => [
                'UserId' => $this->config['user_id'],
                'Password' => $this->config['password'],
            ],
            'TransType' => 'EN_GOODS',
            'ReserveNum' => $id,
            'MerchantId' => $this->config['user_id'],
            'Amount' => $amount . '',
            'RedirectUrl' => $callbackUrl,
        ];
        $result = $this->_request($url, $params);

        return [
            'token' => $result['Token'],
            'fee' => 0,
        ];
    }

    public function redirect(int $id, string $token, string $callbackUrl)
    {
        $action = 'https://say.shaparak.ir/_ipgw_/MainTemplate/payment/';
        $fields = [
            'token' => $token,
            'language' => 'fa',
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
            'State' => null,
            'token' => null,
            'RefNum' => null,
        ];
        $params = array_merge($default, $params);
        if ($params['State'] != 'OK') {
            throw new LarapayException($this->translateStatus($params['State']));
        }
        if ($params['token'] != $token) {
            throw new LarapayException($this->translateStatus('token-mismatch'));
        }

        $url = $this->url . 'verifyMerchantTrans/';
        $data = [
            'WSContext' => [
                'UserId' => $this->config['user_id'],
                'Password' => $this->config['password'],
            ],
            'Token' => $token,
            'RefNum' => $params['RefNum'],
        ];
        $result = $this->_request($url, $data);

        return [
            'fee' => 0,
            'card' => null,
            'result' => $result['Result'],
            'reference_id' => $result['RefNum'],
            'tracking_code' => $result['RefNum'],
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

            $result = json_decode($response->getBody()->getContents(), true);
            if ($result['Result'] != 'erSucceed') {
                throw new LarapayException($this->translateStatus($result['Result']));
            }

            return $result;
        } catch (BadResponseException $e) {
            throw new LarapayException($e->getMessage());
        }
    }
}
