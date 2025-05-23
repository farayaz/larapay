<?php

namespace Farayaz\Larapay\Gateways;

use Farayaz\Larapay\Exceptions\LarapayException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redirect;

class Polam extends GatewayAbstract
{
    protected $url = 'https://polam.io/invoice/';

    protected $statuses = [
        '100' => 'نوع درخواست باید POST باشد',
        '101' => 'api_key ارسال نشده است یا صحیح نیست',
        '102' => 'مبلغ ارسال نشده است یا کمتر از 1000 ریال است',
        '103' => 'آدرس بازگشت ارسال نشده است',
        '301' => 'خطایی در برقراری با سرور بانک رخ داده است',
        '302' => 'ترمینال غیرفعال است.',
        '200' => 'شناسه پرداخت صحیح نیست',
        '201' => 'پرداخت انجام نشده است',
        '202' => 'پرداخت کنسل شده است یا خطایی در مراحل پرداخت رخ داده است.',
        '203' => 'آدرس بازگشت و یا آدرس درخواست کننده با دامنه ثبت شده یکی نیست.',
        '204' => 'آدرس آی پی هاست دامنه نامعتبر است.',

        'token-mismatch' => 'عدم تطبیق توکن',
    ];

    protected $requirements = ['api_key'];

    public function request(
        int $id,
        int $amount,
        string $nationalId,
        string $mobile,
        string $callbackUrl
    ): array {
        $url = $this->url . 'request';
        $params = [
            'api_key' => $this->config['api_key'],
            'amount' => $amount,
            'return_url' => $callbackUrl,
        ];
        $result = $this->_request('post', $url, $params);
        if ($result['status'] != 1) {
            throw new LarapayException($this->translateStatus($result['errorCode']));
        }

        return [
            'token' => $result['invoice_key'],
            'fee' => $this->fee($amount),
        ];
    }

    public function redirect(int $id, string $token, string $callbackUrl)
    {
        return Redirect::to($this->url . 'pay/' . $token);
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
            'invoice_key' => null,
        ];
        $params = array_merge($default, $params);

        if ($params['invoice_key'] != $token) {
            throw new LarapayException($this->translateStatus('token-mismatch'));
        }

        $url = $this->url . 'check/' . $token;
        $data = [
            'api_key' => $this->config['api_key'],
        ];
        $result = $this->_request('post', $url, $data);
        if ($result['status'] != 1) {
            throw new LarapayException($this->translateStatus($result['errorCode']));
        }

        return [
            'result' => $this->translateStatus($result['status']),
            'card' => null,
            'tracking_code' => $result['bank_code'],
            'reference_id' => $result['bank_code'],
            'fee' => $this->fee($amount),
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

            return $result;
        } catch (RequestException $e) {
            throw new LarapayException($e->getMessage());
        } catch (ConnectionException $e) {
            throw new LarapayException($this->translateStatus('connection-exception'));
        }
    }

    public function fee(int $amount): int
    {
        $fee = 5_000;
        if ($amount > 500_000) {
            $fee = min(50_000, round($amount * 0.01));
        }

        return $fee;
    }
}
