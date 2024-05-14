<?php

namespace Farayaz\Larapay\Gateways;

use Farayaz\Larapay\Exceptions\LarapayException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redirect;

final class Azkivam extends GatewayAbstract
{
    protected $url = 'https://api.azkivam.com';

    protected $statuses = [
        '0' => 'ازکی‌وام: Request finished successfully',
        '1' => 'ازکی‌وام: خطای داخلی اتفاق افتاده است.',
        '12' => 'ازکی‌وام: فروشگاه فعال نیست.',
        '13' => 'ازکی‌وام: شماره موبایل معتبر نیست.',
        '15' => 'ازکی‌وام: Access Denied',
        '16' => 'ازکی‌وام: Transaction already reversed',
        '17' => 'ازکی‌وام: Ticket Expired',
        '18' => 'ازکی‌وام: Signature Invalid',
        '19' => 'ازکی‌وام: Ticket unpayable',
        '2' => 'ازکی‌وام: Resource Not Found',
        '20' => 'ازکی‌وام: شماره موبایل مشتری با ثبت نام شده در درگاه ازکی‌وام یکسان نیست.',
        '21' => 'ازکی‌وام: اعتبار کافی نیست.',
        '28' => 'ازکی‌وام: تراکنش قابل تأیید نیست.',
        '32' => 'ازکی‌وام: Invalid Invoice Data',
        '33' => 'ازکی‌وام: Contract is not started',
        '34' => 'ازکی‌وام: Contract is expired',
        '4' => 'ازکی‌وام: Malformed Data',
        '44' => 'ازکی‌وام: Validation exception',
        '5' => 'ازکی‌وام: Data Not Found',
        '51' => 'ازکی‌وام: Request data is not valid',
        '59' => 'ازکی‌وام: Transaction not reversible',
        '60' => 'ازکی‌وام: Transaction must be in verified state',
    ];

    protected $requirements = ['merchant_id', 'api_key'];

    public function request(
        int $id,
        int $amount,
        string $nationalId,
        string $mobile,
        string $callbackUrl
    ): array {
        $params = [
            'amount' => $amount,
            'redirect_uri' => $callbackUrl,
            'fallback_uri' => $callbackUrl,
            'provider_id' => $id,
            'mobile_number' => $mobile,
            'merchant_id' => $this->config['merchant_id'],
            'items' => [],
        ];
        $result = $this->_request('/payment/purchase', $params);

        return [
            'token' => $result['result']['ticket_id'],
            'fee' => 0,
        ];
    }

    public function redirect(int $id, string $token, string $callbackUrl)
    {
        return Redirect::to('https://panel.azkivam.com/payment/?ticketId=' . $token);
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
        ];
        $params = array_merge($default, $params);
        if ($params['status'] != 'Done') {
            throw new LarapayException($this->translateStatus($params['status']));
        }

        $params = [
            'ticket_id' => $token,
        ];
        $this->_request('/payment/verify', $params);

        return [
            'fee' => 0,
            'card' => null,
            'result' => 'Done',
            'reference_id' => null,
            'tracking_code' => $token,
        ];
    }

    private function _request(string $url, array $data)
    {
        $plain = $url . '#' . time() . '#POST#' . $this->config['api_key'];
        $signature = bin2hex(@openssl_encrypt($plain, 'AES-256-CBC', hex2bin($this->config['api_key']), OPENSSL_RAW_DATA));

        try {
            $result = Http::timeout(10)
                ->withHeaders([
                    'Signature' => $signature,
                    'MerchantId' => $this->config['merchant_id'],
                ])
                ->post($this->url . $url, $data)
                ->json();
        } catch (ConnectionException $e) {
            throw new LarapayException($this->translateStatus('connection-exception'));
        }

        if ($result['rsCode'] != '0') {
            throw new LarapayException($this->translateStatus($result['rsCode']));
        }

        return $result;
    }
}
