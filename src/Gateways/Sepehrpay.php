<?php

namespace Farayaz\Larapay\Gateways;

use Farayaz\Larapay\Exceptions\GatewayException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;

final class Sepehrpay extends GatewayAbstract
{
    private $url = 'https://mabna.shaparak.ir:8081/V1/PeymentApi/';

    protected $statuses = [
        '0' => '0',
        '-1' => 'تراکنش پیدا نشد.',
        '-2' => 'عدم تطابق ip / تراکنش قبلا Reserve شده است.',
        '-3' => 'ها Exception خطای – عمومی خطای Total Error',
        '-4' => 'امکان درخواست برای این تراکنش وجود ندارد.',
        '-5' => 'آدرس IP نامعتبر می‌باشد.',
        '-6' => 'عدم فعال بودن سرویس برگشت تراکنش برای پذیرنده',
    ];

    protected $requirements = ['terminalId'];

    function request($id, $amount, $callback)
    {
        $url = $this->url . 'GetToken';
        $params = [
            'Amount'        => $amount,
            'callbackURL'   => $callback,
            'invoiceID'     => $id,
            'terminalID'    => $this->config['terminalId'],
        ];

        $result = $this->_request($url, $params);
        if ($result['Status'] != 0) {
            throw new GatewayException($this->translateStatus($result['Status']));
        }

        return [
            'token' => $result['Accesstoken'],
            'fee' => $this->fee($amount),
        ];
    }

    function redirect($id, $token)
    {
        $action = "https://mabna.shaparak.ir:8080";
        $fields = [
            'token'         => $token,
            'terminalID'    => $this->config['terminalId'],
        ];
        return view('larapay::redirector', compact('action', 'fields'));
    }

    function verify($id, $amount, $token, array $params = [])
    {
        $default = [
            'respcode'          => null,
            'cardnumber'        => null,
            'rrn'               => null,
            'tracenumber'       => null,
            'amount'            => null,
            'invoiceid'         => null,
            'terminalid'        => null,
            'digitalreceipt'    => null,
        ];
        $params = array_merge($default, $params);

        if ($params['trackId'] != $token) {
            throw new GatewayException($this->translateStatus('token-mismatch'));
        }
        if ($params['respcode'] != 0) {
            throw new GatewayException($this->translateStatus($params['respcode']));
        }

        if (
            $amount != $params['amount'] ||
            $id != $params['invoiceid'] ||
            $this->config['terminalId'] != $params['terminalid']
        ) {
            throw new GatewayException($this->translateStatus('token-mismatch'));
        }

        $url = $this->url . 'Advice';
        $data = [
            'Tid'               => $this->config['terminalId'],
            'digitalreceipt'    => $params['digitalreceipt'],
        ];
        $result = $this->_request($url, $data);

        if ($result['Status'] == 'Ok' &&  $result['ReturnId'] == $amount) {
            return [
                'result'        => $params['respcode'] . ' - ' . $result['Status'],
                'card'          => $params['cardnumber'],
                'reference_id'  => $params['rrn'],
                'tracking_code' => $params['tracenumber'],
                'fee'           => $this->fee($amount),
            ];
        }
        throw new GatewayException($this->translateStatus($result['Status']));
    }

    private function _request($url, $data)
    {
        $client = new Client();

        try {
            $response = $client->request(
                "POST",
                $url,
                [
                    'headers'   => [
                        'Content-Type'  => 'application/json',
                        'Accept'        => 'application/json',
                    ],
                    'json'      => $data,
                    'timeout'   => 10,
                ]
            );
            return json_decode($response->getBody(), true);
        } catch (BadResponseException $e) {
            throw new GatewayException($e->getMessage());
        }
    }
}
