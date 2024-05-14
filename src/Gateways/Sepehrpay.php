<?php

namespace Farayaz\Larapay\Gateways;

use Farayaz\Larapay\Exceptions\LarapayException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\View;

final class Sepehrpay extends GatewayAbstract
{
    private $url = 'https://sepehr.shaparak.ir:8081/V1/PeymentApi/';

    protected $statuses = [
        '-1' => 'تراکنش پیدا نشد.',
        '-2' => 'عدم تطابق ip / تراکنش قبلا Reserve شده است.',
        '-3' => 'Exception خطای - عمومی خطای Total Error',
        '-4' => 'امکان درخواست برای این تراکنش وجود ندارد.',
        '-5' => 'آدرس IP نامعتبر می‌باشد.',
        '-6' => 'عدم فعال بودن سرویس برگشت تراکنش برای پذیرنده',
    ];

    protected $requirements = ['terminalId'];

    public function request(
        int $id,
        int $amount,
        string $nationalId,
        string $mobile,
        string $callbackUrl
    ): array {
        $url = $this->url . 'GetToken';
        $params = [
            'Amount' => $amount,
            'callbackURL' => $callbackUrl,
            'invoiceID' => $id,
            'terminalID' => $this->config['terminalId'],
        ];

        $result = $this->_request($url, $params);
        if ($result['Status'] != 0) {
            throw new LarapayException($this->translateStatus($result['Status']));
        }

        return [
            'token' => $result['Accesstoken'],
            'fee' => $this->fee($amount),
        ];
    }

    public function redirect(int $id, string $token, string $callbackUrl)
    {
        $action = 'https://sepehr.shaparak.ir:8080';
        $fields = [
            'token' => $token,
            'terminalID' => $this->config['terminalId'],
        ];

        return View::make('larapay::redirector', compact('action', 'fields'));
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
            'respcode' => null,
            'cardnumber' => null,
            'rrn' => null,
            'tracenumber' => null,
            'amount' => null,
            'invoiceid' => null,
            'terminalid' => null,
            'digitalreceipt' => null,
        ];
        $params = array_merge($default, $params);

        if ($params['trackId'] != $token) {
            throw new LarapayException($this->translateStatus('token-mismatch'));
        }
        if ($params['respcode'] != 0) {
            throw new LarapayException($this->translateStatus($params['respcode']));
        }

        if (
            $amount != $params['amount'] || $id != $params['invoiceid'] || $this->config['terminalId'] != $params['terminalid']
        ) {
            throw new LarapayException($this->translateStatus('token-mismatch'));
        }

        $url = $this->url . 'Advice';
        $data = [
            'Tid' => $this->config['terminalId'],
            'digitalreceipt' => $params['digitalreceipt'],
        ];
        $result = $this->_request($url, $data);

        if ($result['Status'] == 'Ok' && $result['ReturnId'] == $amount) {
            return [
                'result' => $params['respcode'] . ' - ' . $result['Status'],
                'card' => $params['cardnumber'],
                'reference_id' => $params['rrn'],
                'tracking_code' => $params['tracenumber'],
                'fee' => $this->fee($amount),
            ];
        }
        throw new LarapayException($this->translateStatus($result['Status']));
    }

    private function _request(string $url, array $data)
    {
        try {
            return Http::timeout(10)
                ->post($url, $data)
                ->throw()
                ->json();
        } catch (RequestException $e) {
            throw new LarapayException($e->getMessage());
        } catch (ConnectionException $e) {
            throw new LarapayException($this->translateStatus('connection-exception'));
        }
    }
}
