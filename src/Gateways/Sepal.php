<?php

namespace Farayaz\Larapay\Gateways;

use Farayaz\Larapay\Exceptions\LarapayException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redirect;

class Sepal extends GatewayAbstract
{
    protected $url = 'https://sepal.ir/';

    protected $statuses = [
    ];

    protected $requirements = ['api_key'];

    public function request(
        int $id,
        int $amount,
        string $nationalId,
        string $mobile,
        string $callbackUrl,
        array $allowedCards = []
    ): array {
        $params = [
            'apiKey' => $this->config['api_key'],
            'amount' => $amount,
            'callbackUrl' => $callbackUrl,
            'invoiceNumber' => $id,
            // 'payerName' => '',
            'payerMobile' => $mobile,
            // 'payerEmail' => '',
            'description' => $id,
        ];
        $result = $this->_request('post', $this->url . 'api/request.json', $params);

        return [
            'token' => $result['paymentNumber'],
            'fee' => 0,
        ];
    }

    public function redirect(int $id, string $token, string $callbackUrl)
    {
        return Redirect::to($this->url . 'payment/' . $token);
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
            'status' => null,
            'paymentNumber' => null,
            'invoiceNumber' => null,
        ];
        $params = array_merge($default, $params);
        if ($params['status'] != 1) {
            throw new LarapayException($this->translateStatus('failed'));
        }
        if ($params['paymentNumber'] != $token) {
            throw new LarapayException($this->translateStatus('token-mismatch'));
        }
        if ($params['invoiceNumber'] != $id) {
            throw new LarapayException($this->translateStatus('id-mismatch'));
        }

        $data = [
            'apiKey' => $this->config['api_key'],
            'paymentNumber' => $token,
            'invoiceNumber' => $id,
        ];
        $result = $this->_request('post', $this->url . 'api/verify.json', $data);

        return [
            'fee' => 0,
            'card' => $result['cardNumber'] ?? null,
            'result' => 'OK',
            'reference_id' => $token,
            'tracking_code' => $token,
        ];
    }

    private function _request(string $method, string $url, array $data = [], array $headers = [], $timeout = 10)
    {
        try {
            $result = Http::timeout($timeout)
                ->withHeaders($headers)
                ->$method($url, $data)
                ->throw()
                ->json();

            if ($result['status'] != 1) {
                throw new LarapayException($this->translateStatus($result['message'] ?? 'unknown error'));
            }

            return $result;
        } catch (RequestException $e) {
            $message = $e->getMessage();
            throw new LarapayException($message);
        } catch (ConnectionException $e) {
            throw new LarapayException($this->translateStatus('connection-exception'));
        }
    }
}
