<?php

namespace Farayaz\Larapay\Gateways;

use Farayaz\Larapay\Exceptions\GatewayException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;

final class PardakhtNovin extends GatewayAbstract
{
    protected $url = 'https://pna.shaparak.ir/';

    protected $statuses = [
        'Canceled By User'  => 'لغو شده توسط مشتری',
        'erAAS_InvalidUseridOrPass' => 'کد کاربری یا رمز عبور صحیح نیست',
        'erMts_InvalidUseridOrPass' => 'رمز یا کد کاربری معتبر نمی‌باشد',

        'token-mismatch'    => 'مغایرت توکن بازگشتی',
    ];

    protected $requirements = [
        'userId',
        'password',
        'terminalId',
    ];

    function request(
        int $id,
        int $amount,
        string $callback
    ): array {
        $url = $this->url . 'ref-payment2/RestServices/mts/generateTokenWithNoSign/';
        $params = [
            'WSContext'     => [
                'UserId'    => $this->config['userId'],
                'Password'  => $this->config['password'],
            ],
            'TransType'     => 'EN_GOODS',
            'ReserveNum'    => $id,
            'Amount'        => $amount,
            'TerminalId'    => $this->config['terminalId'],
            'RedirectUrl'   => $callback,
        ];
        $result = $this->_request($url, $params);

        if ($result['Result'] != 'erSucceed') {
            throw new GatewayException($this->translateStatus($result['Result']));
        }

        return [
            'token' => $result['Token'],
            'fee'   => $this->fee($amount),
        ];
    }

    function redirect(int $id, string $token)
    {
        $action = $this->url . '_ipgw_/payment/';
        $fields = [
            'token' => $token,
        ];
        return view('larapay::redirector', compact('action', 'fields'));
    }

    function verify(
        int $id,
        int $amount,
        string $token,
        array $params = []
    ): array {
        $default = [
            'State'             => null,
            'token'             => null,
            'CardMaskPan'       => null,
            'CustomerRefNum'    => null,
            'RefNum'            => null,
            'TraceNo'           => null,
        ];
        $params = array_merge($default, $params);

        if ($params['State'] != 'OK') {
            throw new GatewayException($this->translateStatus($params['State']));
        }

        if ($params['token'] != $token) {
            throw new GatewayException($this->translateStatus('token-mismatch'));
        }

        $url = $this->url . 'ref-payment2/RestServices/mts/verifyMerchantTrans/';
        $data = [
            'WSContext'     => [
                'UserId'    => $this->config['userId'],
                'Password'  => $this->config['password'],
            ],
            'Token'         => $params['token'],
            'RefNum'        => $params['RefNum']
        ];
        $result = $this->_request($url, $data);

        if ($result['Result'] != 'erSucceed') {
            throw new GatewayException($this->translateStatus($result['Result']));
        }

        return [
            'result'        => $params['State'],
            'card'          => $params['CardMaskPan'],
            'tracking_code' => $params['CustomerRefNum'],
            'reference_id'  => $params['TraceNo'],
            'fee'           => $this->fee($amount),
        ];
    }

    private function _request(string $url, array $data)
    {
        $client = new Client();
        try {
            $response = $client->request(
                'POST',
                $url,
                [
                    'headers'   => [
                        'Content-Type'  => 'application/json',
                        'Accept'        => 'application/json',
                    ],
                    'json'      => $data,
                    'timeout'   => 10,
                ]
            );
            return json_decode($response->getBody(), true);
        } catch (BadResponseException $e) {
            throw new GatewayException($e->getMessage());
        }
    }
}
