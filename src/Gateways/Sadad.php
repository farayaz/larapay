<?php

namespace Farayaz\Larapay\Gateways;

use Farayaz\Larapay\Exceptions\LarapayException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redirect;

class Sadad extends GatewayAbstract
{
    protected $url = 'https://sadad.shaparak.ir/vpg/api/v0/';

    protected $statuses = [];

    protected $requirements = [
        'terminal_id',
        'merchant_id',
        'key',
    ];

    public function request(
        int $id,
        int $amount,
        string $nationalId,
        string $mobile,
        string $callbackUrl
    ): array {
        $data = [
            'TerminalId' => $this->config['terminal_id'],
            'MerchantId' => $this->config['merchant_id'],
            'Amount' => $amount,
            'SignData' => $this->encrypt_pkcs7($this->config['terminal_id'] . ';' . $id . ';' . $amount),
            'ReturnUrl' => $callbackUrl,
            'LocalDateTime' => Date::now()->format('m/d/Y g:i:s a'),
            'OrderId' => $id,
        ];
        $result = $this->_request('post', 'Request/PaymentRequest', $data);
        if ($result['ResCode'] != 0) {
            throw new LarapayException($result['Description']);
        }

        return [
            'token' => $result['Token'],
            'fee' => 0,
        ];
    }

    public function redirect(int $id, string $token, string $callbackUrl)
    {
        return Redirect::to('https://sadad.shaparak.ir/VPG/Purchase?Token=' . $token);
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
            'OrderId' => null,
            'token' => null,
            'ResCode' => null,
            'PrimaryAccNo' => null,
        ];
        $params = array_merge($default, $params);
        if ($params['token'] != $token) {
            throw new LarapayException($this->translateStatus('token-mismatch'));
        }
        if ($params['OrderId'] != $id) {
            throw new LarapayException($this->translateStatus('id-mismatch'));
        }
        if ($params['ResCode'] != 0) {
            throw new LarapayException($this->translateStatus($params['ResCode']));
        }

        $data = [
            'Token' => $token,
            'SignData' => $this->encrypt_pkcs7($token),
        ];
        $result = $this->_request('post', 'Advice/Verify', $data);
        if ($result['ResCode'] != 0) {
            throw new LarapayException($this->translateStatus($result['ResCode']));
        }

        return [
            'result' => '0',
            'card' => $params['PrimaryAccNo'],
            'tracking_code' => $result['SystemTraceNo'],
            'reference_id' => $result['RetrievalRefNo'],
            'fee' => 0,
        ];
    }

    protected function _request($method, $url, array $data = [], array $headers = [])
    {
        try {
            return Http::timeout(10)
                ->withHeaders($headers)
                ->$method($this->url . $url, $data)
                ->throw()
                ->json();
        } catch (RequestException $e) {
            throw new LarapayException($e->getMessage());
        } catch (ConnectionException $e) {
            throw new LarapayException($this->translateStatus('connection-exception'));
        }
    }

    protected function encrypt_pkcs7($str)
    {
        $key = base64_decode($this->config['key']);
        $ciphertext = openssl_encrypt($str, 'DES-EDE3', $key, OPENSSL_RAW_DATA);

        return base64_encode($ciphertext);
    }
}
