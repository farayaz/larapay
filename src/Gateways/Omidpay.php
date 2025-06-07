<?php

namespace Farayaz\Larapay\Gateways;

use Farayaz\Larapay\Exceptions\LarapayException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\View;

class Omidpay extends GatewayAbstract
{
    protected $url = 'https://ref.omidpayment.ir/ref-payment/RestServices/mts/';

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
        $result = $this->_request('post', $url, $params);

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

        $url = $this->url . 'inquiryMerchantToken/';
        $data = [
            'WSContext' => [
                'UserId' => $this->config['user_id'],
                'Password' => $this->config['password'],
            ],
            'Token' => $token,
        ];
        $result1 = $this->_request('post', $url, $data);

        $url = $this->url . 'verifyMerchantTrans/';
        $data = [
            'WSContext' => [
                'UserId' => $this->config['user_id'],
                'Password' => $this->config['password'],
            ],
            'Token' => $token,
            'RefNum' => $params['RefNum'],
        ];
        $result2 = $this->_request('post', $url, $data);

        return [
            'fee' => 0,
            'card' => str_replace('********', '******', $result1['MaskPan']),
            'result' => $result2['Result'],
            'reference_id' => $result2['RefNum'],
            'tracking_code' => $result1['Rrn'],
        ];
    }

    private function _request($method, $url, array $data = [], array $headers = [], $timeout = 10)
    {
        try {
            $result = Http::timeout($timeout)
                ->withHeaders($headers)
                ->$method($url, $data)
                ->throw()
                ->json();

            if ($result['Result'] != 'erSucceed') {
                throw new LarapayException($this->translateStatus($result['Result']));
            }

            return $result;
        } catch (RequestException $e) {
            throw new LarapayException($e->getMessage());
        } catch (ConnectionException $e) {
            throw new LarapayException($this->translateStatus('connection-exception'));
        }
    }
}
