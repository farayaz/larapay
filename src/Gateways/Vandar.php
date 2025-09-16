<?php

namespace Farayaz\Larapay\Gateways;

use Farayaz\Larapay\Exceptions\LarapayException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redirect;

class Vandar extends GatewayAbstract
{
    protected $url = 'https://ipg.vandar.io/';

    protected $statuses = [];

    protected $requirements = [
        'api_key',
    ];

    public function request(
        int $id,
        int $amount,
        string $nationalId,
        string $mobile,
        string $callbackUrl,
        array $allowedCards = []
    ): array {
        $data = [
            'api_key' => $this->config['api_key'],
            'amount' => $amount,
            'callback_url' => $callbackUrl,
            'mobile_number' => $mobile,
            'factorNumber' => $id,
            'description' => 'transaction:' . $id,
            'comment' => $id,
            'valid_card_number' => [],
        ];
        $result = $this->_request('post', 'api/v4/send', $data);

        return [
            'token' => $result['token'],
            'fee' => 0,
        ];
    }

    public function redirect(int $id, string $token, string $callbackUrl)
    {
        return Redirect::to($this->url . 'v4/' . $token);
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
            'token' => null,
            'payment_status' => null,
        ];
        $params = array_merge($default, $params);

        if ($params['payment_status'] != 'OK') {
            throw new LarapayException($this->translateStatus('failed'));
        }
        if ($params['token'] != $token) {
            throw new LarapayException($this->translateStatus('token-mismatch'));
        }

        $data = [
            'api_key' => $this->config['api_key'],
            'token' => $token,
        ];
        $result = $this->_request('post', 'api/v4/verify', $data);

        return [
            'result' => 'OK',
            'card' => $result['cardNumber'],
            'tracking_code' => $result['transId'],
            'reference_id' => $result['transId'],
            'fee' => $result['wage'] + $result['shaparakWage'],
        ];
    }

    private function _request($method, $url, array $data = [], array $headers = [])
    {
        try {
            $result = Http::timeout(10)
                ->withHeaders($headers)
                ->$method($this->url . $url, $data)
                ->throw()
                ->json();

            if ($result['status'] != '1') {
                throw new LarapayException(implode(', ', $result['errors']));
            }

            return $result;
        } catch (RequestException $e) {
            $message = $e->getMessage();
            if ($e->response->status() == 422) {
                $result = $e->response->json();
                $message = implode(', ', $result['errors']);
            }
            throw new LarapayException($message);
        } catch (ConnectionException $e) {
            throw new LarapayException($this->translateStatus('connection-exception'));
        }
    }
}
