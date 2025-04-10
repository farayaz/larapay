<?php

namespace Farayaz\Larapay\Gateways;

use Farayaz\Larapay\Exceptions\LarapayException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redirect;

class NextPay extends GatewayAbstract
{
    protected $url = 'https://nextpay.org/nx/gateway/';

    protected $statuses = [
        '0' => 'پرداخت تکمیل و با موفقیت انجام شده است',
        '-1' => 'منتظر ارسال تراکنش و ادامه پرداخت',
        '-2' => 'پرداخت رد شده توسط کاربر یا بانک',
        '-3' => 'پرداخت در حال انتظار جواب بانک',
        '-4' => 'پرداخت لغو شده است',
        '-20' => 'کد api_key ارسال نشده است',
        '-21' => 'کد trans_id ارسال نشده است',
        '-22' => 'مبلغ ارسال نشده',
        '-23' => 'لینک ارسال نشده',
        '-24' => 'مبلغ صحیح نیست',
        '-25' => 'تراکنش قبلا انجام و قابل ارسال نیست',
        '-26' => 'مقدار توکن ارسال نشده است',
        '-27' => 'شماره سفارش صحیح نیست',
        '-28' => 'مقدار فیلد سفارشی [custom_json_fields] از نوع json نیست',
        '-29' => 'کد بازگشت مبلغ صحیح نیست',
        '-30' => 'مبلغ کمتر از حداقل پرداختی است',
        '-31' => 'صندوق کاربری موجود نیست',
        '-32' => 'مسیر بازگشت صحیح نیست',
        '-33' => 'کلید مجوز دهی صحیح نیست',
        '-34' => 'کد تراکنش صحیح نیست',
        '-35' => 'ساختار کلید مجوز دهی صحیح نیست',
        '-36' => 'شماره سفارش ارسال نشد است',
        '-37' => 'شماره تراکنش یافت نشد',
        '-38' => 'توکن ارسالی موجود نیست',
        '-39' => 'کلید مجوز دهی موجود نیست',
        '-40' => 'کلید مجوزدهی مسدود شده است',
        '-41' => 'خطا در دریافت پارامتر، شماره شناسایی صحت اعتبار که از بانک ارسال شده موجود نیست',
        '-42' => 'سیستم پرداخت دچار مشکل شده است',
        '-43' => 'درگاه پرداختی برای انجام درخواست یافت نشد',
        '-44' => 'پاسخ دریاف شده از بانک نامعتبر است',
        '-45' => 'سیستم پرداخت غیر فعال است',
        '-46' => 'درخواست نامعتبر',
        '-47' => 'کلید مجوز دهی یافت نشد [حذف شده]',
        '-48' => 'نرخ کمیسیون تعیین نشده است',
        '-49' => 'تراکنش مورد نظر تکراریست',
        '-50' => 'حساب کاربری برای صندوق مالی یافت نشد',
        '-51' => 'شناسه کاربری یافت نشد',
        '-52' => 'حساب کاربری تایید نشده است',
        '-60' => 'ایمیل صحیح نیست',
        '-61' => 'کد ملی صحیح نیست',
        '-62' => 'کد پستی صحیح نیست',
        '-63' => 'آدرس پستی صحیح نیست و یا بیش از ۱۵۰ کارکتر است',
        '-64' => 'توضیحات صحیح نیست و یا بیش از ۱۵۰ کارکتر است',
        '-65' => 'نام و نام خانوادگی صحیح نیست و یا بیش از ۳۵ کاکتر است',
        '-66' => 'تلفن صحیح نیست',
        '-67' => 'نام کاربری صحیح نیست یا بیش از ۳۰ کارکتر است',
        '-68' => 'نام محصول صحیح نیست و یا بیش از ۳۰ کارکتر است',
        '-69' => 'آدرس ارسالی برای بازگشت موفق صحیح نیست و یا بیش از ۱۰۰ کارکتر است',
        '-70' => 'آدرس ارسالی برای بازگشت ناموفق صحیح نیست و یا بیش از ۱۰۰ کارکتر است',
        '-71' => 'موبایل صحیح نیست',
        '-72' => 'بانک پاسخگو نبوده است لطفا با نکست پی تماس بگیرید',
        '-73' => 'مسیر بازگشت دارای خطا میباشد یا بسیار طولانیست',
        '-90' => 'بازگشت مبلغ بدرستی انجام شد',
        '-91' => 'عملیات ناموفق در بازگشت مبلغ',
        '-92' => 'در عملیات بازگشت مبلغ خطا رخ داده است',
        '-93' => 'موجودی صندوق کاربری برای بازگشت مبلغ کافی نیست',
        '-94' => 'کلید بازگشت مبلغ یافت نشد',
    ];

    protected $requirements = ['api_key'];

    public function request(
        int $id,
        int $amount,
        string $nationalId,
        string $mobile,
        string $callbackUrl
    ): array {
        $params = [
            'api_key' => $this->config['api_key'],
            'order_id' => $id,
            'amount' => $amount,
            'callback_uri' => $callbackUrl,
            'currency' => 'IRR',
            'customer_phone' => $mobile,
            // 'custom_json_fields' =>
            // 'payer_name' => '',
            'payer_desc' => $id,
            'auto_verify' => false,
            // 'allowed_card' => '',
        ];
        $result = $this->_request('post', 'token', $params);
        if ($result['code'] !== -1) {
            throw new LarapayException($this->translateStatus($result['code']));
        }

        return [
            'token' => $result['trans_id'],
            'fee' => 0,
        ];
    }

    public function redirect(int $id, string $token, string $callbackUrl)
    {
        return Redirect::to($this->url . 'payment/' . $token);
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
            'trans_id' => null,
            'order_id' => null,
            'amount' => null,
        ];
        $params = array_merge($default, $params);

        if ($params['trans_id'] != $token) {
            throw new LarapayException($this->translateStatus('token-mismatch'));
        }
        if ($params['order_id'] != $token) {
            throw new LarapayException($this->translateStatus('id-mismatch'));
        }
        if ($params['amount'] != $amount) {
            throw new LarapayException($this->translateStatus('amount-mismatch'));
        }

        $data = [
            'api_key' => $this->config['api_key'],
            'trans_id' => $token,
            'amount' => $amount,
            'currency' => 'IRR',
        ];
        $result = $this->_request('post', 'verify', $data);
        if ($result['code'] != 0) {
            throw new LarapayException($this->translateStatus($result['code']));
        }

        return [
            'result' => 'OK',
            'card' => $result['card_holder'],
            'tracking_code' => $result['Shaparak_Ref_Id'],
            'reference_id' => $result['Shaparak_Ref_Id'],
            'fee' => 0,
        ];
    }

    private function _request(string $method, string $path, array $data = [], array $headers = [], $timeout = 10)
    {
        $url = $this->url . $path;
        try {
            $result = Http::timeout($timeout)
                ->asJson()
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
