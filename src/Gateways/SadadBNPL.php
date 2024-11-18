<?php

namespace Farayaz\Larapay\Gateways;

use Farayaz\Larapay\Exceptions\LarapayException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redirect;

class SadadBNPL extends GatewayAbstract
{
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
            'OrderId' => $id,
            'ReturnUrl' => $callbackUrl,
            'ApplicationName' => 'Bnpl',
            'UserId' => $mobile,
            'CardHolderIdentity' => $mobile,
            'PanAuthenticationType' => 2,
            'LocalDateTime' => Date::now()->format('m/d/Y g:i:s a'),
            'NationalCode' => $nationalId,
        ];
        $url = 'https://bnpl.sadadpsp.ir/Bnpl/GenerateKey';
        $result = $this->_request($url, 'post', $data);
        if ($result['ResponseCode'] != 0) {
            throw new LarapayException($result['Message']);
        }

        return [
            'token' => $result['BnplKey'],
            'fee' => 0,
        ];
    }

    public function redirect(int $id, string $token, string $callbackUrl)
    {
        return Redirect::to('https://bnpl.sadadpsp.ir/Home?key=' . $token);
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
            'Token' => null,
            'ResCode' => null,
        ];
        $params = array_merge($default, $params);
        if ($params['OrderId'] != $id) {
            throw new LarapayException($this->translateStatus('id-mismatch'));
        }
        if ($params['ResCode'] != 0) {
            throw new LarapayException($this->translateStatus($params['ResCode']));
        }

        $data = [
            'Token' => $params['Token'],
            'SignData' => $this->encrypt_pkcs7($token),
        ];
        $url = 'https://sadad.shaparak.ir/api/v0/BnplAdvice/Verify';
        $result = $this->_request($url, 'post', $data);
        if ($result['ResCode'] != 0) {
            throw new LarapayException($this->translateStatus($result['ResCode']));
        }

        return [
            'result' => '0',
            'card' => $result['OrderId'],
            'tracking_code' => $result['SystemTraceNo'],
            'reference_id' => $result['RetrivalRefNo'],
            'fee' => 0,
        ];
    }

    protected function _request($url, $method, array $data = [], array $headers = [])
    {
        try {
            $result = Http::timeout(10)
                ->withHeaders($headers)
                ->$method($url, $data)
                ->throw()
                ->json();

            return $result;
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
