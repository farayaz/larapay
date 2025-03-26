<h1 align="center">Larapay | لاراپِی</h1>
<p align="center">
    <a href="https://github.com/farayaz/larapay"><img src="https://img.shields.io/github/stars/farayaz/larapay" alt="GitHub Repo stars"></a>
    <a href="https://packagist.org/packages/farayaz/larapay"><img src="https://img.shields.io/packagist/dt/farayaz/larapay" alt="Total Downloads"></a>
    <a href="https://packagist.org/packages/farayaz/larapay"><img src="https://img.shields.io/packagist/v/farayaz/larapay" alt="Latest Stable Version"></a>
    <a href="https://packagist.org/packages/farayaz/larapay"><img src="https://img.shields.io/packagist/l/farayaz/larapay" alt="License"></a>
</p>
Larapay is a Laravel package for integrating Iranian payment gateways.

لاراپی یک پکیج لاراول برای اتصال به درگاه‌های پرداختی ایرانی است.

## Gateways | درگاه‌ها

| Class               | Name (en)                                      | Name (fa)                    | Requirements                                                                           |
| ------------------- | ---------------------------------------------- | ---------------------------- | -------------------------------------------------------------------------------------- |
| **Azkivam**         | [Azkivam](https://azkivam.com/)                | ازکی وام                     | `merchant_id`,`api_key`                                                                |
| **BehPardakht**     | [Beh Pardakht Mellat](https://behpardakht.com) | به‌پرداخت ملت                 | `terminal_id`, `username`, `password`, `is_credit`                                     |
| **Digipay**         | [Digipay](https://www.mydigipay.com)           | دیجی‌پی                       | `username`, `password`, `client_id`, `client_secret`                                   |
| **IdPay**           | [IdPay](https://idpay.ir)                      | آیدی‌پی                       | `apiKey`, `sandbox`                                                                    |
| **IranKish**        | [Iran Kish](https://www.irankish.com)          | ایران کیش                    | `terminalId`, `password`, `acceptorId`, `pubKey`                                       |
| **IsipaymentSamin** | [Isipayment Samin](https://isipayment.ir)      | ایزایران ثمین                | `merchant_code`, `merchant_password`, `terminal_code`, `type`, `number_of_installment` |
| **Keepa**           | [Keepa - Kipaa](https://keepa.ir)              | کیپا                         | `token`                                                                                |
| **MehrIran**        | [MehrIran](https://qmb.ir)                     | بانک مهر ایران               | `terminal_id`, `merchant_nid`, `encrypt_key`                                           |
| **Omidpay**         | [Omidpay - Sayan Card](https://omidpayment.ir) | امید پی (سایان کارت)         | `user_id`, `password`                                                                  |
| **PardakhtNovin**   | [Pardakht Novin](https://pna.co.ir)            | پرداخت نوین                  | `userId`, `password`, `terminalId`                                                     |
| **Payir**           | [Pay.ir](https://www.pay.ir)                   | پی.آی‌آر                      | `api`                                                                                  |
| **PayPing**         | [PayPing](https://payping.ir)                  | پی پینگ                      | `token`                                                                                |
| **PEP**             | [PEP](https://pep.co.ir)                       | پرداخت الکترونیک پاسارگاد    | `username`, `password`, `terminal_number`                                              |
| **Polam**           | [Polam(Poolam)](https://polam.io)              | پولام                        | `api_key`                                                                              |
| **RefahBeta**       | [Refah Beta](https://beta.refah-bank.ir)       | بانک رفاه بتا                | `client_id` , `client_secret`, `api_key`, `number_of_installments`                     |
| **Sadad**           | [Sadad](https://sadadpsp.ir)                   | پرداخت الکترونیک سداد (ملی)  | `terminal_id`, `merchant_id`, `key`                                                    |
| **SadadBNPL**       | [SadadBNPL](https://sadadpsp.ir)               | پرداخت الکترونیک سداد (ملی)  | `terminal_id`, `merchant_id`, `key`                                                    |
| **Sep**             | [Saman Electronic Payment](https://www.sep.ir) | پرداخت الکترونیک سامان (سپ)  | `terminalId`                                                                           |
| **SepehrPay**       | [Sepehr Pay](https://www.sepehrpay.com)        | پرداخت الکترونیک سپهر (مبنا) | `terminalId`                                                                           |
| **Shepa**           | [Shepa](https://shepa.com)                     | شپا                          | `api`                                                                                  |
| **SnappPay**        | [SnappPay](https://snapppay.ir)                | اسنپ‌پی                       | `username`, `password`, `client_id`, `client_secret`                                   |
| **TejaratBajet**    | [Tejarat Bajet](https://mybajet.ir)            | بانک تجارت - باجت            | `client_id`, `client_secret`, `sandbox`                                                |
| **Vandar**          | [Vandar](https://vandar.io)                    | وندار                        | `api_key`                                                                              |
| **ZarinPal**        | [Zarin Pal](https://www.zarinpal.com)          | زرین پال                     | `merchant_id`                                                                          |
| **Zibal**           | [Zibal](https://zibal.ir)                      | زیبال                        | `merchant`                                                                             |

If you don't find the gateway you want, let us know or contribute to add it
****
اگر درگاه مورد نظر خود را پیدا نکردید، به ما اطلاع دهید یا در اضافه کردن آن مشارکت کنید

## Benefits | مزایا

- Simple | ساده
- Flexibility | انعطاف‌پذیری
- Fee Calculation | محاسبه هزینه تراکنش

## Install | نصب

You can install the package via composer:

شما می‌توانید با استفاده از composer پکیج را نصب کنید

```bash
composer require farayaz/larapay
```

## Usage | استفاده

To make the payment, 3 steps must be done:

برای انجام پرداخت ۳ مرحله می‌بایست انجام شود:

### Step 1: get token | مرحله ۱: دریافت توکن

```php
use Farayaz\Larapay\Exceptions\LarapayException;
use Larapay;

$gatewayClass = 'ZarinPal';
$gatewayConfig = [ // gateway config | تنظیمات درگاه
    'merchant_id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
];

$amount = 10000;
$id = 1230; // transaction id | شماره تراکنش
$callbackUrl = route('api.transactions.verify', $id);
$nationalId = '1234567890';
$mobile = '09131234567';

try {
    $result = Larapay::gateway($gatewayClass, $gatewayConfig)
        ->request(
            id: $id,
            amount: $amount,
            callbackUrl: $callbackUrl,
            nationalId: $nationalId,
            mobile: $mobile
        );
} catch (LarapayException $e) {
    throw $e;
}

// store token in db | ذخیره توکن در دیتابیس
$result['token'];
$result['fee'];
```

### Step 2: redirect | مرحله ۲: ریدایرکت

Transfer the user to gateway with the received token:

انتقال کاربر به درگاه با توکن دریافت شده:

```php
try {
    return Larapay::gateway($gatewayClass, $gatewayConfig)
        ->redirect($id, $token, $callbackUrl);
} catch (LarapayException $e) {
    throw $e;
}
```

### Step 3: verify | مرحله ۳: تایید

Checking the payment status after the user returns from the gateway:

بررسی وضعیت پرداخت پس از بازگشت کاربر از درگاه:

```php
$params = $request->all();
try {
    $result = Larapay::gateway($gatewayClass, $gatewayConfig)
        ->verify(
            id: $id,
            amount: $amount,
            token: $token,
            params: $params
        );
} catch (LarapayException $e) {
    // transaction failed | تراکنش ناموفق
    throw $e;
}

// transaction verified | تراکنش موفق
$result['result'];
$result['reference_id'];
$result['tracking_code'];
$result['card'];
$result['fee'];
```
