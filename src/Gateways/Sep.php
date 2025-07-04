<?php

namespace Farayaz\Larapay\Gateways;

use Farayaz\Larapay\Exceptions\LarapayException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\View;

class Sep extends GatewayAbstract
{
    protected $url = 'https://sep.shaparak.ir/';

    protected $statuses = [
        '1' => 'لغو شده توسط مشتری',
        '2' => 'پرداخت با موفقیت انجام شد',
        '3' => 'پرداخت انجام نشد',
        '4' => 'کاربر در بازه زمان تعیین شده پاسخی ارسال نکرده است.',
        '5' => 'پارامترهای ارسالی نامعتر است',
        '8' => 'آدرس سرور پذیرنده نا معتبر است.',
        '10' => 'توکن ارسال شده یافت نشد.',
        '11' => 'فقط تراکنش های توکنی قابل پرداخت می باشد.',
        '12' => 'شماره ترمینال یافت نشد.',

        '0' => 'موفق',
        '-6' => 'بیش از نیم ساعت از زمان اجرای تراکنش گذشته است.',
        '-2' => 'تراکنش یافت نشد.',
        '-100' => 'ورودی اشتباه',
        '-101' => 'ورودی اشتباه',
        '-102' => 'خطای پردازش بانکی',
        '-103' => 'عدم دریافت پاسخ',
        '-104' => 'ترمینال غیر فعال می باشد.',
        '-105' => 'ترمینال یافت نشد.',
        '-106' => 'آیپی مجاز نمی باشد.',
        '-107' => 'امکان وریفای تراکنش مورد نظر وچود ندارد',
        '-108' => 'امکان وریفای سریع برای این ترمینال وجود ندارد',
        '-109' => 'مبالغ ارسالی برای تسویه به چند حساب بیش از حد مجاز است. ',
        '-110' => 'مبالغ ارسال برای تسویه به چند حساب با ریزمبالغ مطالبقت ندارد',
        '-111' => 'امکان تایید تراکنش وجود ندارد',
        '-112' => 'امکان برگشت تراکنش وجود ندارد',
        '-113' => 'کد ملی و شماره همراه وارد شده معتبر نیست یا مطعلغ به یک فرد نمیباشد',
        '-114' => 'خطا در تراکنش منکسی',
        '-115' => 'کد ملی و شماره همراه متعلق به یک فرد نمیباشد',
        '-116' => 'رسید دیجیتالی نامعتبر میباشد',
        '-117' => 'ارسال توکن یا شناسه خرید برای استعلام الزامی است',
        '-118' => 'قبضی با این مشخصات یافت نشد.',
    ];

    protected $requirements = ['terminalId'];

    public function request(
        int $id,
        int $amount,
        string $nationalId,
        string $mobile,
        string $callbackUrl
    ): array {
        $url = $this->url . 'OnlinePG/OnlinePG';
        $params = [
            'action' => 'token',
            'TerminalId' => $this->config['terminalId'],
            'ResNum' => $id,
            'Amount' => $amount,
            'RedirectUrl' => $callbackUrl,
        ];
        $result = $this->_request('post', $url, $params);

        if ($result['status'] != 1) {
            throw new LarapayException($this->translateStatus($result['errorDesc']));
        }

        return [
            'token' => $result['token'],
            'fee' => $this->fee($amount),
        ];
    }

    public function redirect(int $id, string $token, string $callbackUrl)
    {
        $action = $this->url . 'OnlinePG/OnlinePG';
        $fields = [
            'token' => $token,
            'language' => 'fa',
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
            'MID' => null,
            'TerminalId' => null,
            'RefNum' => null,
            'ResNum' => null,
            'State' => null,
            'TraceNo' => null,
            'Amount' => null,
            'AffectiveAmount' => null,
            'Wage' => null,
            'Rrn' => null,
            'SecurePan' => null,
            'Status' => null,
            'Token' => null,
            'HashedCardNumber' => null,
        ];
        $params = array_merge($default, $params);

        if ($params['Status'] != '2') {
            throw new LarapayException($this->translateStatus($params['Status']));
        }

        $url = $this->url . 'verifyTxnRandomSessionkey/ipg/VerifyTransaction';
        $data = [
            'RefNum' => $params['RefNum'],
            'TerminalNumber' => $this->config['terminalId'],
        ];
        $result = $this->_request('post', $url, $data);

        if ($result['ResultCode'] != 0) {
            throw new LarapayException($this->translateStatus($result['ResultCode']));
        }

        if ($amount != $result['TransactionDetail']['OrginalAmount']) {
            throw new LarapayException($this->translateStatus('amount-mismatch'));
        }

        return [
            'card' => $params['SecurePan'],
            'tracking_code' => $params['ResNum'],
            'reference_id' => $params['Rrn'],
            'result' => $params['State'],
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
