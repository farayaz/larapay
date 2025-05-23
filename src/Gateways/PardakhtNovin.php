<?php

namespace Farayaz\Larapay\Gateways;

use Farayaz\Larapay\Exceptions\LarapayException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\View;

class PardakhtNovin extends GatewayAbstract
{
    protected $url = 'https://pna.shaparak.ir/';

    protected $statuses = [
        'Canceled By User' => 'لغو شده توسط مشتری',
        'erAAS_InvalidUseridOrPass' => 'کد کاربری یا رمز عبور صحیح نیست',
        'erMts_InvalidUseridOrPass' => 'رمز یا کد کاربری معتبر نمی‌باشد',

        'token-mismatch' => 'مغایرت توکن بازگشتی',
    ];

    protected $requirements = [
        'userId',
        'password',
        'terminalId',
    ];

    public function request(
        int $id,
        int $amount,
        string $nationalId,
        string $mobile,
        string $callbackUrl
    ): array {
        $url = $this->url . 'ref-payment2/RestServices/mts/generateTokenWithNoSign/';
        $params = [
            'WSContext' => [
                'UserId' => $this->config['userId'],
                'Password' => $this->config['password'],
            ],
            'TransType' => 'EN_GOODS',
            'ReserveNum' => $id,
            'Amount' => $amount,
            'TerminalId' => $this->config['terminalId'],
            'RedirectUrl' => $callbackUrl,
        ];
        $result = $this->_request('post', $url, $params);

        if ($result['Result'] != 'erSucceed') {
            throw new LarapayException($this->translateStatus($result['Result']));
        }

        return [
            'token' => $result['Token'],
            'fee' => $this->fee($amount),
        ];
    }

    public function redirect(int $id, string $token, string $callbackUrl)
    {
        $action = $this->url . '_ipgw_/payment/';
        $fields = [
            'token' => $token,
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
            'State' => null,
            'token' => null,
            'CardMaskPan' => null,
            'CustomerRefNum' => null,
            'RefNum' => null,
            'TraceNo' => null,
        ];
        $params = array_merge($default, $params);

        if ($params['State'] != 'OK') {
            throw new LarapayException($this->translateStatus($params['State']));
        }

        if ($params['token'] != $token) {
            throw new LarapayException($this->translateStatus('token-mismatch'));
        }

        $url = $this->url . 'ref-payment2/RestServices/mts/verifyMerchantTrans/';
        $data = [
            'WSContext' => [
                'UserId' => $this->config['userId'],
                'Password' => $this->config['password'],
            ],
            'Token' => $params['token'],
            'RefNum' => $params['RefNum'],
        ];
        $result = $this->_request('post', $url, $data);

        if ($result['Result'] != 'erSucceed') {
            throw new LarapayException($this->translateStatus($result['Result']));
        }

        return [
            'result' => $params['State'],
            'card' => $params['CardMaskPan'],
            'tracking_code' => $params['CustomerRefNum'],
            'reference_id' => $params['TraceNo'],
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
}
