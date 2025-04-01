<?php

namespace Farayaz\Larapay\Gateways;

use Farayaz\Larapay\Exceptions\LarapayException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redirect;

class ZarinPal extends GatewayAbstract
{
    protected $statuses = [
        -9 => 'خطای اعتبار سنجی',
        -10 => 'ای پی و يا مرچنت كد پذيرنده صحيح نيست',
        -11 => 'مرچنت کد فعال نیست لطفا با تیم پشتیبانی ما تماس بگیرید',
        -12 => 'تلاش بیش از حد در یک بازه زمانی کوتاه.',
        -15 => 'ترمینال شما به حالت تعلیق در آمده با تیم پشتیبانی تماس بگیرید',
        -16 => 'سطح تاييد پذيرنده پايين تر از سطح نقره اي است.',
        100 => 'عملیات موفق',
        -30 => 'اجازه دسترسی به تسویه اشتراکی شناور ندارید',
        -31 => 'حساب بانکی تسویه را به پنل اضافه کنید مقادیر وارد شده واسه تسهیم درست نیست',
        -32 => '',
        -33 => 'درصد های وارد شده درست نیست',
        -34 => 'مبلغ از کل تراکنش بیشتر است',
        -35 => 'تعداد افراد دریافت کننده تسهیم بیش از حد مجاز است',
        -40 => '',
        -50 => 'مبلغ پرداخت شده با مقدار مبلغ در وریفای متفاوت است',
        -51 => 'پرداخت ناموفق',
        -52 => 'خطای غیر منتظره با پشتیبانی تماس بگیرید',
        -53 => 'اتوریتی برای این مرچنت کد نیست',
        -54 => 'اتوریتی نام errors عتبر است',
        101 => 'تراکنش وریفای شده',

        'NOK' => 'پرداخت ناموفق NOK',
        'token-mismatch' => 'عدم تطبیق توکن',
    ];

    protected $requirements = ['merchant_id'];

    public function request(
        int $id,
        int $amount,
        string $nationalId,
        string $mobile,
        string $callbackUrl
    ): array {
        $url = 'https://api.zarinpal.com/pg/v4/payment/request.json';
        $data = [
            'merchant_id' => $this->config['merchant_id'],
            'amount' => $amount,
            'description' => $id . '-' . $amount,
            'callback_url' => $callbackUrl,
        ];

        $result = $this->_request('post', $url, $data);
        $fee = ($result['fee_type'] == 'Merchant' ? $result['fee'] : 0);

        return [
            'token' => $result['authority'],
            'fee' => $fee,
        ];
    }

    public function redirect(int $id, string $token, string $callbackUrl)
    {
        return Redirect::to('https://www.zarinpal.com/pg/StartPay/' . $token);
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
            'Authority' => null,
            'Status' => null,
        ];
        $params = array_merge($default, $params);

        if ($params['Authority'] != $token) {
            throw new LarapayException($this->translateStatus('token-mismatch'));
        }
        if ($params['Status'] != 'OK') {
            throw new LarapayException($this->translateStatus($params['Status']));
        }

        $url = 'https://api.zarinpal.com/pg/v4/payment/verify.json';
        $data = [
            'merchant_id' => $this->config['merchant_id'],
            'amount' => $amount,
            'authority' => $token,
        ];
        $result = $this->_request('post', $url, $data);

        $fee = ($result['fee_type'] == 'Merchant' ? $result['fee'] : 0);

        return [
            'result' => $result['message'],
            'card' => $result['card_pan'],
            'tracking_code' => $result['ref_id'],
            'reference_id' => $result['ref_id'],
            'fee' => $fee,
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

            if (! empty($result['errors'])) {
                throw new LarapayException(
                    $this->translateStatus($result['errors']['code'])
                );
            }

            if ($result['data']['code'] != 100) {
                $message = $result['data']['code'];
                throw new LarapayException($this->translateStatus($message));
            }

            return $result['data'];
        } catch (RequestException $e) {
            $message = $e->getMessage();
            $result = $e->response->json();
            if (! empty($result['errors'])) {
                $message = $this->translateStatus($result['errors']['code']);
            }

            throw new LarapayException($message);
        } catch (ConnectionException $e) {
            throw new LarapayException($this->translateStatus('connection-exception'));
        }
    }
}
