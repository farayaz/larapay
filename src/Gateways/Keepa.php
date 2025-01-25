<?php

namespace Farayaz\Larapay\Gateways;

use Farayaz\Larapay\Exceptions\LarapayException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\View;

final class Keepa extends GatewayAbstract
{
    protected $url = 'https://api.kipaa.ir/ipg/v1/supplier/';

    protected $statuses = [
        'amount-mismatch' => 'مغایرت مبلغ پرداختی',
        'token-mismatch' => 'عدم تطبیق توکن بازگشتی',
        'verify-status-false' => 'تایید اولیه نا موفق',
        'confirm-status-false' => 'تایید ثانویه نا موفق',

        100 => 'تراکنش با موفقیت ایجاد شد.',
        101 => 'خطا در ثبت تراکنش',
        102 => 'انصراف کاربر در مراحل میانی پرداخت',
        103 => 'بازگشت به سایت پذیرنده',

        200 => 'عملیات با موفقیت انجام شد.',
        404 => 'آدرس URL درخواستی شما وجود ندارد.',
        405 => 'توکن نامعتبر است.',
        406 => 'مقادیر ورودی قابل پردازش نیست.',
        416 => 'مبلغ وارد شده نامعتبر است.',
        500 => 'خطایی در سرور رخ داده است. لطفا بعدا تلاش کنید.',
        503 => 'سرویس به صورت موقت در دسترس نمی‌باشد.',
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
        $result = $this->_request('post', $url, $params);

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
            'state' => null,
            'msg' => null,
        ];
        $params = array_merge($default, $params);

        if ($params['payment_token'] != $token) {
            throw new LarapayException($this->translateStatus('token-mismatch'));
        }

        if ($params['state'] != 100) {
            throw new LarapayException($params['state'] ?? $params['msg']);
        }

        $data = [
            'payment_token' => $token,
            'reciept_number' => $params['reciept_number'],
        ];

        $url = $this->url . 'verify_transaction';
        $result = $this->_request('post', $url, $data);
        if ($result['Content']['Status'] != true) {
            throw new LarapayException($this->translateStatus('verify-status-false'));
        }
        if ($amount != $result['Content']['Amount']) {
            throw new LarapayException($this->translateStatus('amount-mismatch'));
        }

        $url = $this->url . 'confirm_transaction';
        $result = $this->_request('post', $url, $data);
        if ($result['Content']['Status'] != true) {
            throw new LarapayException($this->translateStatus('confirm-status-false'));
        }

        return [
            'fee' => 0,
            'card' => null,
            'result' => 'OK',
            'reference_id' => $result['Content']['ConfirmTransactionNumber'],
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

    private function _request($method, $url, array $data = [], array $headers = [], $timeout = 10)
    {
        $headers['Authorization'] = 'Bearer ' . $this->config['token'];
        try {
            $result = Http::timeout($timeout)
                ->withHeaders($headers)
                ->$method($url, $data)
                ->throw()
                ->json();

            if (! $result['Success']) {
                $message = $this->translateStatus($result['Message'] ?? $result['Status']);
                throw new LarapayException($message);
            }

            return $result;
        } catch (RequestException $e) {
            throw new LarapayException($e->getMessage());
        } catch (ConnectionException $e) {
            throw new LarapayException($this->translateStatus('connection-exception'));
        }
    }
}
