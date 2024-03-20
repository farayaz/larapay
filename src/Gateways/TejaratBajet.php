<?php

namespace Farayaz\Larapay\Gateways;

use Farayaz\Larapay\Exceptions\LarapayException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;

final class TejaratBajet extends GatewayAbstract
{
    protected $statuses = [
    ];

    protected $requirements = [
        'client_id',
        'client_secret',
        'sandbox',
    ];

    public function request(
        int $id,
        int $amount,
        string $nationalId,
        string $mobile,
        string $callbackUrl
    ): array {
        $headers = [
            'Authorization' => 'Bearer ' . $this->authenticate(),
        ];

        $url = $this->_url('/customers/' . $nationalId . '/balance');
        $result = $this->_request('GET', $url, [], $headers);
        if ($result['result']['balance'] < $amount) {
            throw new LarapayException('عدم موجودی کافی: ' . number_format($result['result']['balance']));
        }

        $url = $this->_url('/customers/' . $nationalId . '/purchases/authorization?trackId=' . $id);
        $data = [
            'amount' => $amount,
            'description' => $id,
        ];
        $this->_request('POST', $url, $data, $headers);

        return [
            'token' => $id,
            'fee' => 0,
        ];
    }

    public function redirect(int $id, string $token, string $callbackUrl)
    {
        return View::make('larapay::tejarat-bajet', compact('callbackUrl'));
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
            'otp' => null,
        ];
        $params = array_merge($default, $params);

        $headers = [
            'Authorization' => 'Bearer ' . $this->authenticate(),
        ];

        // purchases
        $url = $this->_url('/customers/' . $nationalId . '/purchases?trackId=' . $id);
        $data = [
            'otp' => $params['otp'],
            'amount' => $amount,
        ];
        $this->_request('POST', $url, $data, $headers);

        // advice
        $url = $this->_url('/customers/' . $nationalId . '/purchases/advice?trackId=' . $id);
        $result = $this->_request('POST', $url, [], $headers);

        return [
            'result' => $result['message'],
            'card' => null,
            'tracking_code' => $result['result']['referenceNumber'],
            'reference_id' => $result['result']['referenceNumber'],
            'fee' => 0,
        ];
    }

    public function authenticate()
    {
        return Cache::remember(__CLASS__ . ':token', 3500, function () {
            $url = $this->_url('auth/token');

            $data = [
                'client_id' => $this->config['client_id'],
                'client_secret' => $this->config['client_secret'],
                'grant_type' => 'client_credentials',
                'scope' => '',
            ];

            $result = $this->_request('POST', $url, $data);

            return $result['result']['accessToken'];
        });
    }

    private function _url($url)
    {
        return 'https://fct-api.stts.ir/api/'
            . ($this->config['sandbox'] ? 'vNext/' : 'v1/')
            . $url;
    }

    private function _request($method, $url, array $data = [], array $headers = [])
    {
        $client = new Client;

        try {
            $response = $client->request(
                $method,
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
            if ($e->hasResponse()) {
                $message = $e->getResponse()->getStatusCode() . ': ' . $e->getMessage();
                $response = json_decode($e->getResponse()->getBody(), true);
                $message = $response['detail'] ?? $response['title'] ?? $message;
            }
            throw new LarapayException($message);
        }
    }
}
