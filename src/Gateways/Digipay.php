<?php

namespace Farayaz\Larapay\Gateways;

use Farayaz\Larapay\Exceptions\LarapayException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redirect;

final class Digipay extends GatewayAbstract
{
    protected $url = 'https://api.mydigipay.com/digipay/api/';

    protected $statuses = [
        'id-mismatch' => 'عدم تطبیق شناسه برگشتی',
        '401-authenticate' => 'اطلاعات ورود اشتباه است',
    ];

    protected $requirements = [
        'username',
        'password',
        'client_id',
        'client_secret',
    ];

    public function request(
        int $id,
        int $amount,
        string $callbackUrl,
        string $nationalId,
        string $mobile
    ): array {
        $url = $this->url . 'businesses/ticket?type=0';
        $data = [
            'amount' => $amount,
            'cellNumber' => null,
            'providerId' => $id,
            'redirectUrl' => $callbackUrl,
            'userType' => 2,
        ];
        $headers = [
            'Authorization' => 'Bearer ' . $this->authenticate(),
        ];
        $result = $this->_request($url, $data, $headers);

        if (($result['result']['status'] ?? -1) != 0) {
            $message = $data['result']['message'] ?? 'unknown error';
            throw new LarapayException($message);
        }

        return [
            'token' => $result['ticket'],
            'fee' => 0,
        ];
    }

    public function redirect($id, $token)
    {
        return Redirect::to($this->url . 'purchases/ipg/pay/' . $token);
    }

    public function verify(int $id, int $amount, string $token, array $params = []): array
    {
        $default = [
            'amount' => null,
            'providerId' => null,
            'trackingCode' => null,
            'rrn' => null,
            'pspName' => null,
            'redirectUrl' => null,
            'fundProviderCode' => null,
            'resultStatus' => null,
            'type' => null,
        ];
        $params = array_merge($default, $params);

        if ($params['providerId'] != $id) {
            throw new LarapayException($this->translateStatus('id-mismatch'));
        }

        $url = $this->url . 'purchases/verify/' . $params['trackingCode'];
        $headers = [
            'Authorization' => 'Bearer ' . $this->authenticate(),
        ];
        $result = $this->_request($url, [], $headers);

        if (($result['result']['status'] ?? -1) != 0) {
            $message = $result['result']['message'] ?? 'unknown error';
            throw new LarapayException($message);
        }

        return [
            'result' => $result['result']['status'],
            'card' => $result['maskedPan'],
            'tracking_code' => $result['trackingCode'],
            'reference_id' => $result['rrn'],
            'fee' => 0,
        ];
    }

    private function authenticate()
    {
        if (Cache::get(__CLASS__ . 'token')) {
            return Cache::get(__CLASS__ . 'token');
        }

        $url = $this->url . 'oauth/token';
        $data = [
            'username' => $this->config['username'],
            'password' => $this->config['password'],
            'grant_type' => 'password',
        ];
        $headers = [
            'Authorization' => 'Basic ' . base64_encode($this->config['client_id'] . ':' . $this->config['client_secret']),
        ];

        $result = $this->_request($url, $data, $headers);

        Cache::put(__CLASS__ . 'token', $result['access_token'], $result['expires_in'] - 10);

        return $result['access_token'];
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
                        ...$headers,
                    ],
                    'json' => $data,
                    'timeout' => 10,
                ]
            );

            return json_decode($response->getBody(), true);
        } catch (BadResponseException $e) {
            $message = $e->getMessage();
            if ($e->getResponse()?->getStatusCode() == 401) {
                $message = '401 Unauthorized';
                if ($e->getRequest()->getUri()->getPath() == '/digipay/api/oauth/token') {
                    $message = $this->translateStatus('401-authenticate');
                }
            }
            throw new LarapayException($message);
        }
    }
}
