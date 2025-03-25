<?php

namespace Farayaz\Larapay\Gateways;

use Farayaz\Larapay\Exceptions\LarapayException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redirect;
use Morilog\Jalali\Jalalian;

class PEP extends GatewayAbstract
{
    protected $url = 'https://pep.shaparak.ir/dorsa1';

    protected $statuses = [];

    protected $requirements = [
        'username',
        'password',
        'terminal_number',
    ];

    public function request(
        int $id,
        int $amount,
        string $nationalId,
        string $mobile,
        string $callbackUrl
    ): array {
        $data = [
            'amount' => $amount,
            'invoice' => $id,
            'invoiceDate' => Jalalian::now()->format('Y-m-d'),
            'serviceCode' => 8,
            'serviceType' => 'PURCHASE',
            'callbackApi' => $callbackUrl,
            'payerMail' => '',
            'payerName' => '',
            'mobileNumber' => $mobile,
            'terminalNumber' => $this->config['terminal_number'],
            'description' => $id,
            'pans' => '',
            'nationalCode' => $nationalId,
        ];
        $result = $this->_request('post', 'api/payment/purchase', $data);

        return [
            'token' => $result['data']['urlId'],
            'fee' => 0,
        ];
    }

    public function redirect(int $id, string $token, string $callbackUrl)
    {
        return Redirect::to($this->url . $token);
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
            'invoiceId' => null,
            'status' => null,
            'referenceNumber' => null,
            'trackId' => null,
        ];
        $params = array_merge($default, $params);

        if ($params['status'] != 'success') {
            throw new LarapayException($this->translateStatus('failed'));
        }
        if ($params['invoiceId'] != $id) {
            throw new LarapayException($this->translateStatus('id-mismatch'));
        }

        $data = [
            'invoice' => $id,
            'urlId' => $token,
        ];
        $result = $this->_request('post', 'api/payment/confirm-transactions', $data);

        return [
            'result' => 'OK',
            'card' => $result['data']['maskedCardNumber'],
            'tracking_code' => $result['data']['trackId'],
            'reference_id' => $result['data']['referenceNumber'],
            'fee' => 0,
        ];
    }

    private function authenticate()
    {
        if (Cache::get(__CLASS__ . 'token')) {
            return Cache::get(__CLASS__ . 'token');
        }

        $data = [
            'username' => $this->config['username'],
            'password' => $this->config['password'],
        ];
        $result = $this->_request('post', 'token/getToken', $data);

        Cache::put(__CLASS__ . 'token', $result['token'], 300);

        return $result['token'];
    }

    private function _request($method, $url, array $data = [], array $headers = [])
    {
        if ($url != 'token/getToken') {
            $headers['Authorization'] = 'Bearer ' . $this->authenticate();
        }

        try {
            $result = Http::timeout(10)
                ->withHeaders($headers)
                ->$method($this->url . $url, $data)
                ->throw()
                ->json();

            if ($result['resultCode'] != '0') {
                throw new LarapayException($this->translateStatus($result['resultMsg']));
            }

            return $result;
        } catch (RequestException $e) {
            throw new LarapayException($e->getMessage());
        } catch (ConnectionException $e) {
            throw new LarapayException($this->translateStatus('connection-exception'));
        }
    }
}
