<?php

namespace Farayaz\Larapay\Gateways;

use Farayaz\Larapay\Exceptions\LarapayException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\View;

class TejaratBajet extends GatewayAbstract
{
    protected $statuses = [
        'TrackerAlreadyUsed' => 'کد پیگیری تکراری',
    ];

    protected $requirements = [
        'client_id',
        'client_secret',
        'username',
        'password',
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

        $url = 'customers/' . $nationalId . '/balance';
        $result = $this->_request('get', $url, [], $headers);
        if ($result['result']['balance'] < $amount) {
            throw new LarapayException('باجت: عدم موجودی کافی. موجودی: ' . number_format($result['result']['balance']) . ' ریال');
        }

        $url = 'customers/' . $nationalId . '/purchases/authorization?trackId=' . $id;
        $data = [
            'amount' => $amount,
            'description' => $id,
        ];
        $this->_request('post', $url, $data, $headers);

        return [
            'token' => $id,
            'fee' => 0,
        ];
    }

    public function redirect(int $id, string $token, string $callbackUrl)
    {
        return View::make('larapay::otp', compact('callbackUrl'));
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
        $url = 'customers/' . $nationalId . '/purchases?trackId=' . $id;
        $data = [
            'otp' => $params['otp'],
            'amount' => $amount,
            'description' => $id,
        ];
        $this->_request('post', $url, $data, $headers);

        // advice
        $url = 'customers/' . $nationalId . '/purchases/advice?trackId=' . $id;
        $result = $this->_request('post', $url, [], $headers, 20);

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
        if (Cache::get(__METHOD__)) {
            return Cache::get(__METHOD__);
        }
        $data = [
            'client_id' => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
            'grant_type' => 'password',
            'username' => $this->config['username'],
            'password' => $this->config['password'],
        ];
        $result = $this->_request('post', 'token', $data);
        Cache::put(__METHOD__, $result['access_token'], $result['expires_in'] - 10);

        return $result['access_token'];
    }

    private function _request($method, $url, array $data = [], array $headers = [], $timeout = 10)
    {
        $as = 'asForm';
        $fullUrl = 'https://smq.stts.ir/';
        if ($url != 'token') {
            $fullUrl .= 'facilitycustomer/api/v1/';
            $as = 'asJson';
        }
        $fullUrl .= $url;

        try {
            return Http::timeout($timeout)
                ->$as()
                ->withHeaders($headers)
                ->$method($fullUrl, $data)
                ->throw()
                ->json();
        } catch (RequestException $e) {
            $message = $e->getMessage();
            $result = $e->response->json();
            if (isset($result['error'])) {
                $message = $this->translateStatus($result['error']);
            }
            if (isset($result['error_description'])) {
                $message = $this->translateStatus($result['error_description']);
            }
            if (isset($result['title'])) {
                $message = $this->translateStatus($result['title']);
            }
            if (isset($result['detail'])) {
                $message = $result['detail'];
            }
            throw new LarapayException('باجت: ' . $message);
        } catch (ConnectionException $e) {
            throw new LarapayException($this->translateStatus('connection-exception'));
        }
    }
}
