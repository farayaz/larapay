<?php

namespace Farayaz\Larapay\Gateways;

use Farayaz\Larapay\Exceptions\LarapayException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redirect;

class IranDargah extends GatewayAbstract
{
    protected $statuses = [
        '100' => 'تراکنش با موفقیت انجام ‌شده‌ است',
        '101' => 'تراکنش قبلا وریفای شده است',
        '200' => 'اتصال به درگاه بانک با موفقیت انجام ‌شده است',
        '201' => '‌در حال پرداخت در درگاه بانک',
        '403' => 'کد مرچنت صحیح نمی‌باشد',
        '404' => 'تراکنش یافت نشد',
        '-1' => 'کاربر از انجام تراکنش منصرف‌ شده است',
        '-2' => 'اطلاعات ارسالی صحیح نمی‌باشد',
        '-3' => 'URL همخوانی ندارد',
        '-4' => 'آدرس هدایت وجود ندارد',
        '-5' => 'آدرس هدایت معتبر نیست',
        '-6' => 'تراکنش وجود ندارد',
        '-7' => 'آدرس هدایت با آدرس سایت ثبت شده یکسان نیست',
        '-10' => 'مبلغ تراکنش کمتر از 50،000 ریال است',
        '-11' => 'مبلغ تراکنش با مبلغ پرداخت، یکسان نیست. مبلغ برگشت خورد',
        '-12' => 'شماره کارتی که با آن، تراکنش انجام ‌شده است با شماره کارت ارسالی، مغایرت دارد. مبلغ برگشت خورد',
        '-13' => 'تراکنش تکراری است',
        '-20' => 'شناسه تراکنش یافت‌ نشد',
        '-21' => 'مدت زمان مجاز، جهت ارسال به بانک گذشته‌است',
        '-22' => 'تراکنش برای بانک ارسال شده است',
        '-23' => 'خطا در اتصال به درگاه بانک',
        '-30' => 'اشکالی در فرایند پرداخت ایجاد ‌شده است. مبلغ برگشت خورد',
        '-31' => 'خطای ناشناخته',
    ];

    protected $requirements = ['merchant_id', 'sandbox'];

    public function request(
        int $id,
        int $amount,
        string $nationalId,
        string $mobile,
        string $callbackUrl,
        array $allowedCards = []
    ): array {
        $params = [
            'amount' => $amount,
            'callbackURL' => $callbackUrl,
            'orderId' => $id,
            'cardNumber' => '',
            'mobile' => $mobile,
            'description' => $id,
        ];
        $result = $this->_request('payment', $params);

        return [
            'token' => $result['authority'],
            'fee' => 0,
        ];
    }

    public function redirect(int $id, string $token, string $callbackUrl)
    {
        return Redirect::to($this->_url('ird/startpay/' . $token));
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
            'code' => null,
            'message' => null,
            'authority' => null,
            'amount' => null,
            'pan' => null,
            'orderId' => null,
        ];
        $params = array_merge($default, $params);

        if ($params['authority'] != $token) {
            throw new LarapayException($this->translateStatus('token-mismatch'));
        }
        if ($params['code'] != 100) {
            throw new LarapayException($this->translateStatus($params['code']));
        }

        $data = [
            'authority' => $token,
            'amount' => $amount,
            'orderId' => $id,
        ];
        $result = $this->_request('verification', $data);
        if ($result['status'] != 100) {
            throw new LarapayException($this->translateStatus($result['status']));
        }

        return [
            'result' => 'OK',
            'card' => $result['cardNumber'],
            'tracking_code' => $result['refId'],
            'reference_id' => $result['refId'],
            'fee' => 0,
        ];
    }

    private function _request(string $path, array $data = [], array $headers = [], $timeout = 10)
    {
        $data['merchantID'] = $this->config['sandbox'] ? 'TEST' : $this->config['merchant_id'];
        try {
            $result = Http::timeout($timeout)
                ->acceptJson()
                ->withHeaders($headers)
                ->post($this->_url($path), $data)
                ->throw()
                ->json();

            return $result;
        } catch (RequestException $e) {
            $message = $e->getMessage();

            $result = $e->response->json();
            if (! empty($result['message']) || ! empty($result['code'])) {
                $message = $this->translateStatus($result['message'] ?: $result['code']);
            }

            throw new LarapayException($message);
        } catch (ConnectionException $e) {
            throw new LarapayException($this->translateStatus('connection-exception'));
        }
    }

    protected function _url($path)
    {
        $url = 'https://dargaah.com/';
        if ($this->config['sandbox']) {
            $url .= 'sandbox/';
        }

        return $url . $path;
    }
}
