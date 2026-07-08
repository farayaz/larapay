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
        -10 => 'ای پی یا مرچنت کد پذیرنده صحیح نیست',
        -11 => 'مرچنت کد فعال نیست، لطفا با تیم پشتیبانی تماس بگیرید',
        -12 => 'تلاش بیش از دفعات مجاز در یک بازه زمانی کوتاه',
        -13 => 'خطای مربوط به محدودیت تراکنش',
        -14 => 'کال‌بک URL با دامنه ثبت شده درگاه مغایرت دارد',
        -15 => 'درگاه پرداخت به حالت تعلیق در آمده است',
        -16 => 'سطح تایید پذیرنده پایین تر از سطح نقره ای است',
        -17 => 'محدودیت پذیرنده در سطح آبی',
        -18 => 'امکان استفاده از کد درگاه اختصاصی بر روی سایت دیگری را ندارید',
        -19 => 'امکان ایجاد تراکنش برای این ترمینال امکان پذیر نیست',
        100 => 'عملیات موفق',
        -30 => 'اجازه دسترسی به سرویس تسویه اشتراکی شناور را ندارید',
        -31 => 'حساب بانکی تسویه را به پنل اضافه کنید. مقادیر وارد شده برای تسهیم درست نیست',
        -32 => 'مبلغ وارد شده از مبلغ کل تراکنش بیشتر است',
        -33 => 'درصدهای وارد شده صحیح نیست',
        -34 => 'مبلغ وارد شده از مبلغ کل تراکنش بیشتر است',
        -35 => 'تعداد افراد دریافت کننده تسهیم بیش از حد مجاز است',
        -36 => 'حداقل مبلغ جهت تسهیم باید ۱۰۰۰۰ ریال باشد',
        -37 => 'یک یا چند شماره شبای وارد شده برای تسهیم از سمت بانک غیر فعال است',
        -38 => 'خطا، عدم تعریف صحیح شبا',
        -39 => 'خطایی رخ داده است، به امور مشتریان زرین‌پال اطلاع دهید',
        -40 => 'پارامترهای اضافی نامعتبر است',
        -41 => 'حداکثر مبلغ پرداختی ۱۰۰ میلیون تومان است',
        -50 => 'مبلغ پرداخت شده با مقدار مبلغ در وریفای متفاوت است',
        -51 => 'پرداخت ناموفق',
        -52 => 'خطای غیر منتظره، با پشتیبانی تماس بگیرید',
        -53 => 'پرداخت متعلق به این مرچنت کد نیست',
        -54 => 'اتوریتی نامعتبر است',
        -55 => 'تراکنش مورد نظر یافت نشد',
        101 => 'تراکنش وریفای شده',
        -60 => 'امکان ریورس کردن تراکنش با بانک وجود ندارد',
        -61 => 'تراکنش موفق نیست یا قبلا ریورس شده است',
        -62 => 'آی پی درگاه ست نشده است',
        -63 => 'حداکثر زمان (۳۰ دقیقه) برای ریورس کردن این تراکنش منقضی شده است',

        'NOK' => 'پرداخت ناموفق',
    ];

    protected $requirements = ['merchant_id'];

    public function request(
        int $id,
        int $amount,
        string $nationalId,
        string $mobile,
        string $callbackUrl,
        array $allowedCards = []
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
