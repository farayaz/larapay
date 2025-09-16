<?php

namespace Farayaz\Larapay\Gateways;

use Farayaz\Larapay\Exceptions\LarapayException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redirect;

class SnappPay extends GatewayAbstract
{
    private $url = 'https://api.snapppay.ir/';

    protected $statuses = [
        'FAILED' => 'پرداخت ناموفق',
        'not-eligible' => 'not-eligible: قابل پرداخت با اسنپ‌پی نیست',
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
        string $nationalId,
        string $mobile,
        string $callbackUrl,
        array $allowedCards = []
    ): array {
        $result = $this->_request('get', 'api/online/offer/v1/eligible', ['amount' => $amount]);
        if (! $result['response']['eligible']) {
            throw new LarapayException($this->translateStatus('not-eligible'));
        }

        $data = [
            'transactionId' => $id,
            'amount' => $amount,
            'returnURL' => $callbackUrl,
            'paymentMethodTypeDto' => 'INSTALLMENT',
            'mobile' => '+98' . (int) $mobile,
            'externalSourceAmount' => 0,
            'discountAmount' => 0,
            'cartList' => [],
        ];
        $result = $this->_request('post', 'api/online/payment/v1/token', $data);

        return [
            'token' => $result['response']['paymentToken'],
            'fee' => 0,
        ];
    }

    public function redirect(int $id, string $token, string $callbackUrl)
    {
        $extra = '&venture=8q169w5lz1xkpom0gj2d&vtr=JDJhJDEwJFpjMGs3R1BVQ1A2TVUxUlVEUVBPYS5JVGlLRVZaTDNiVS8zMzE0akxHRURDSGlNTmxnWnpL';

        return Redirect::to('https://payment.snapppay.ir/?paymentToken=' . $token . $extra);
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
            'transactionId' => null,
            'state' => null,
            'amount' => null,
        ];
        $params = array_merge($default, $params);

        if ($params['id'] != $id) {
            throw new LarapayException($this->translateStatus('id-mismatch'));
        }
        if ($params['amount'] != $amount) {
            throw new LarapayException($this->translateStatus('amount-mismatch'));
        }
        if ($params['state'] != 'OK') {
            throw new LarapayException($this->translateStatus('FAILED'));
        }

        $data = [
            'paymentToken' => $token,
        ];
        $result = $this->_request('post', 'api/online/payment/v1/verify', $data);
        $result = $this->_request('post', 'api/online/payment/v1/settle', $data);

        return [
            'result' => 'OK',
            'card' => null,
            'reference_id' => $result['response']['transactionId'],
            'tracking_code' => $result['response']['transactionId'],
            'fee' => 0,
        ];
    }

    private function _authenticate()
    {
        if (Cache::get(__CLASS__ . 'token')) {
            return Cache::get(__CLASS__ . 'token');
        }

        $data = [
            'username' => $this->config['username'],
            'password' => $this->config['password'],
            'grant_type' => 'password',
            'scope' => 'online-merchant',
        ];
        $headers = [
            'Authorization' => 'Basic ' . base64_encode($this->config['client_id'] . ':' . $this->config['client_secret']),
        ];
        $result = $this->_request('post', 'api/online/v1/oauth/token', $data, $headers);
        Cache::put(__CLASS__ . 'token', $result['access_token'], $result['expires_in'] - 10);

        return $result['access_token'];
    }

    private function _request(string $method, string $url, array $data, array $headers = [])
    {
        $as = 'asJson';
        if ($url == 'api/online/v1/oauth/token') {
            $as = 'asForm';
        } else {
            $headers['Authorization'] = 'Bearer ' . $this->_authenticate();
        }

        try {
            return Http::timeout(10)
                ->$as()
                ->withHeaders($headers)
                ->$method($this->url . $url, $data)
                ->throw()
                ->json();
        } catch (RequestException $e) {
            $result = $e->response->json();
            $message = $result['errorData']['message'] ?? $e->getMessage();
            throw new LarapayException($message);
        } catch (ConnectionException $e) {
            throw new LarapayException($this->translateStatus('connection-exception'));
        }
    }
}
