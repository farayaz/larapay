<?php

namespace Farayaz\Larapay\Gateways;

use Farayaz\Larapay\Exceptions\LarapayException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redirect;

class Shepa extends GatewayAbstract
{
    protected $statuses = [
        'failed' => 'ناموفق',
    ];

    protected $requirements = ['api'];

    public function request(
        int $id,
        int $amount,
        string $nationalId,
        string $mobile,
        string $callbackUrl,
        array $allowedCards = []
    ): array {
        $url = $this->_url('api/v1/token');
        $params = [
            'api' => $this->config['api'],
            'amount' => $amount,
            'callback' => $callbackUrl,
            'mobile' => $mobile,
            'email' => '',
            'cardnumber' => '',
            'description' => $id,
        ];
        $result = $this->_request('post', $url, $params);

        return [
            'token' => $result['result']['token'],
            'fee' => 0,
        ];
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
            'token' => null,
        ];
        $params = array_merge($default, $params);

        if ($params['token'] != $token) {
            throw new LarapayException($this->translateStatus('token-mismatch'));
        }

        if ($params['status'] != 'success') {
            throw new LarapayException($this->translateStatus($params['status']));
        }

        $url = $this->_url('api/v1/verify');
        $data = [
            'token' => $token,
            'amount' => $amount,
            'api' => $this->config['api'],
        ];
        $result = $this->_request('post', $url, $data);
        if ($amount != $result['result']['amount']) {
            throw new LarapayException($this->translateStatus('amount-mismatch'));
        }

        return [
            'fee' => 0,
            'card' => $result['result']['card_pan'],
            'result' => 'OK',
            'reference_id' => $result['result']['refid'],
            'tracking_code' => $result['result']['transaction_id'],
        ];
    }

    public function redirect(int $id, string $token, string $callbackUrl)
    {
        return Redirect::to($this->_url('v1/' . $token));
    }

    private function _request($method, $url, array $data = [], array $headers = [], $timeout = 10)
    {
        try {
            $result = Http::timeout($timeout)
                ->withHeaders($headers)
                ->$method($url, $data)
                ->throw()
                ->json();

            if (! $result['success']) {
                $message = $this->translateStatus(implode(', ', $result['error']));
                throw new LarapayException($message);
            }
            if (! empty($result['errors'])) {
                $message = $this->translateStatus($result['message'] ?: implode(', ', $result['errors']));
                throw new LarapayException($message);
            }

            return $result;
        } catch (RequestException $e) {
            throw new LarapayException($e->getMessage());
        } catch (ConnectionException $e) {
            throw new LarapayException($this->translateStatus('connection-exception'));
        }
    }

    protected function _url($path)
    {
        $url = 'https://merchant.shepa.com/';
        if ($this->config['api'] == 'sandbox') {
            $url = 'https://sandbox.shepa.com/';
        }

        return $url . $path;
    }
}
