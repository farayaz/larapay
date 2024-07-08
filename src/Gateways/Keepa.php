<?php

namespace Farayaz\Larapay\Gateways;

use Farayaz\Larapay\Exceptions\LarapayException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\View;

final class Keepa extends GatewayAbstract
{
    protected $url = 'https://api.kipaa.ir/ipg/v1/supplier/';

    protected $statuses = [
        'amount-mismatch' => 'مغایرت مبلغ پرداختی',
        'token-mismatch' => 'عدم تطبیق توکن بازگشتی',
        'verify-status-false' => 'تایید اولیه نا موفق',
        'confirm-status-false' => 'تایید ثانویه نا موفق',
    ];

    protected $requirements = ['token'];

    public function request(
        int $id,
        int $amount,
        string $nationalId,
        string $mobile,
        string $callbackUrl
    ): array {
        $url = $this->url . 'request_payment_token';
        $params = [
            'Amount' => $amount,
            'CallBack_Url' => $callbackUrl,
            'mobile' => $mobile,
            'Details' => $id,
        ];
        $result = $this->_request($url, $params);

        return [
            'token' => $result['Content']['payment_token'],
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
            'payment_token' => null,
            'reciept_number' => null,
        ];
        $params = array_merge($default, $params);

        if ($params['payment_token'] != $token) {
            throw new LarapayException($this->translateStatus('token-mismatch'));
        }

        $data = [
            'payment_token' => $token,
            'reciept_number' => $params['reciept_number'],
        ];

        $url = $this->url . 'verify_transaction';
        $result = $this->_request($url, $data);
        if ($result['Content']['Status'] != true) {
            throw new LarapayException($this->translateStatus('verify-status-false'));
        }
        if ($amount != $result['Content']['Amount']) {
            throw new LarapayException($this->translateStatus('amount-mismatch'));
        }

        $url = $this->url . 'confirm_transaction';
        $result = $this->_request($url, $data);
        if ($result['Content']['Status'] != true) {
            throw new LarapayException($this->translateStatus('confirm-status-false'));
        }

        return [
            'fee' => 0,
            'card' => null,
            'result' => $result['Status'],
            'reference_id' => $result['ConfirmTransactionNumber'],
            'tracking_code' => $params['reciept_number'],
        ];
    }

    public function redirect(int $id, string $token, string $callbackUrl)
    {
        $action = 'https://ipg.kipaa.ir';
        $fields = [
            'payment_token' => $token,
        ];

        return View::make('larapay::redirector', compact('action', 'fields'));
    }

    private function _request(string $url, array $data)
    {
        $client = new Client();
        try {
            $response = $client->request(
                'POST',
                $url,
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->config['token'],
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ],
                    'json' => $data,
                    'timeout' => 10,
                ]
            );

            $result = json_decode($response->getBody()->getContents(), true);
            if (! $result['Success']) {
                $message = $this->translateStatus($result['Message'] ?? $result['Status']);
                throw new LarapayException($message);
            }

            return $result;
        } catch (RequestException $e) {
            throw new LarapayException($e->getMessage());
        }
    }
}
