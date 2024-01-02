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

| Class             | Name (en)                                      | Name (fa)                    | Requirements                                         |
| ----------------- | ---------------------------------------------- | ---------------------------- | ---------------------------------------------------- |
| **BehPardakht**   | [Beh Pardakht Mellat](https://behpardakht.com) | به‌پرداخت ملت                 | `terminalId`, `username`, `password`                 |
| **Digipay**       | [Digipay](https://www.mydigipay.com)           | دیجی‌پی                       | `username`, `password`, `client_id`, `client_secret` |
| **IdPay**         | [IdPay](https://idpay.ir)                      | آیدی‌پی                       | `apiKey`, `sandbox`                                  |
| **IranKish**      | [Iran Kish](https://www.irankish.com)          | ایران کیش                    | `terminalId`, `password`, `acceptorId`, `pubKey`     |
| **PardakhtNovin** | [Pardakht Novin](https://pna.co.ir)            | پرداخت نوین                  | `userId`, `password`, `terminalId`                   |
| **Payir**         | [Pay.ir](https://www.pay.ir)                   | پی.آی‌آر                      | `api`                                                |
| **PayPing**       | [PayPing](https://payping.ir)                  | پی پینگ                      | `token`                                              |
| **Polam**         | [Polam(Poolam)](https://polam.io)              | پولام                        | `api_key`                                            |
| **QMB**           | [MehrIran](https://qmb.ir)                     | بانک مهر ایران               | `terminal_id`, `merchant_nid`, `encrypt_key`         |
| **Sep**           | [Saman Electronic Payment](https://www.sep.ir) | پرداخت الکترونیک سامان (سپ)  | `terminalId`                                         |
| **SepehrPay**     | [Sepehr Pay](https://www.sepehrpay.com)        | پرداخت الکترونیک سپهر (مبنا) | `terminalId`                                         |
| **ZarinPal**      | [Zarin Pal](https://www.zarinpal.com)          | زرین پال                     | `merchant_id`                                        |
| **Zibal**         | [Zibal](https://zibal.ir)                      | زیبال                        | `merchant`                                           |
| ...               |                                                |                              |                                                      |

If you don't find the gate you want, let us know or contribute to add it
****
اگر درگاه مورد نظر خود را پیدا نکردید، به ما اطلاع دهید یا در اضافه کردن آن مشارکت کنید

## Benefits | مزایا

-   Simple | ساده
-   Flexibility | انعطاف‌پذیری
-   Fee Calculation | محاسبه هزینه تراکنش

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
use Farayaz\Larapay\Exceptions\GatewayException;
use Larapay;

$gatewayClass = 'ZarinPal';
$gatewayConfig = [ // gateway config | تنظیمات درگاه
    'merchant_id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
];

$amount = 10000;
$id = 1230; // transaction id | شماره تراکنش
$callback = route('api.transactions.verify', $id);

try {
    $result = Larapay::gateway($gatewayClass, $gatewayConfig)
        ->request(
            id: $id,
            amount: $amount,
            callback: $callback
        );
} catch (GatewayException $e) {
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
        ->redirect($id, $token);
} catch (GatewayException $e) {
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
} catch (GatewayException $e) {
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
