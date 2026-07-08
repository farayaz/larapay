<h1 align="center">Larapay | لاراپِی</h1>
<p align="center">
    <a href="https://github.com/farayaz/larapay"><img src="https://img.shields.io/github/stars/farayaz/larapay" alt="GitHub Repo stars"></a>
    <a href="https://packagist.org/packages/farayaz/larapay"><img src="https://img.shields.io/packagist/dt/farayaz/larapay" alt="Total Downloads"></a>
    <a href="https://packagist.org/packages/farayaz/larapay"><img src="https://img.shields.io/packagist/v/farayaz/larapay" alt="Latest Stable Version"></a>
    <a href="https://packagist.org/packages/farayaz/larapay"><img src="https://img.shields.io/packagist/l/farayaz/larapay" alt="License"></a>
    <a href="https://packagist.org/packages/farayaz/larapay"><img src="https://img.shields.io/packagist/php-v/farayaz/larapay" alt="PHP Version"></a>
</p>

Larapay is a Laravel package for integrating Iranian payment gateways.

لاراپی یک پکیج لاراول برای اتصال به درگاه‌های پرداختی ایرانی است.

---

## Requirements | نیازمندی‌ها

- **PHP**: `^8.2`
- **Laravel**: `^12.0` | `^13.0`

---

## Installation | نصب

```bash
composer require farayaz/larapay
```

---

## Configuration | تنظیمات

No configuration file is required. Just pass the gateway config directly when calling the gateway.

نیازی به فایل کانفیگ جداگانه نیست. تنظیمات درگاه را مستقیماً هنگام فراخوانی ارسال کنید.

Each gateway has its own required parameters. See the [Gateways table](#gateways--درگاه‌ها) below.

هر درگاه پارامترهای مورد نیاز خود را دارد. به جدول درگاه‌ها مراجعه کنید.

---

## Gateways | درگاه‌ها

| Class                | Name (en)                                      | Name (fa)                    | Requirements                                                                           | Features   |
| -------------------- | ---------------------------------------------- | ---------------------------- | -------------------------------------------------------------------------------------- | ---------- |
| **AsanPardakht**     | [AsanPardakht](https://asanpardakht.ir)        | آسان پرداخت (آپ)             | `username`, `password`, `merchant_configuration_id`                                    | —          |
| **Azkivam**          | [Azkivam](https://azkivam.com/)                | ازکی وام                     | `merchant_id`, `api_key`                                                               | —          |
| **BehPardakht**      | [Beh Pardakht Mellat](https://behpardakht.com) | به‌پرداخت ملت                 | `terminal_id`, `username`, `password`, `is_credit`                                     | —          |
| **Bitpay**           | [Bitpay](https://bitpay.ir/)                   | بیت پی                       | `api`, `sandbox`                                                                       | —          |
| **Digipay**          | [Digipay](https://www.mydigipay.com)           | دیجی‌پی                       | `username`, `password`, `client_id`, `client_secret`                                   | —          |
| **ECD**              | [Electronic Card Damavand](https://ecd-co.ir)  | پرداخت الکترونیک دماوند      | `terminal_number`, `hash_key`                                                          | —          |
| **FanavaCard**       | [FanavaCard](https://fanavacard.ir)            | فن‌آوا کارت                   | `user_id`, `password`                                                                  | —          |
| **IdPay**            | [IdPay](https://idpay.ir)                      | آیدی‌پی                       | `apiKey`, `sandbox`                                                                    | —          |
| **IranDargah**       | [IranDargah](https://irandargah.com)           | ایران درگاه                  | `merchant_id`, `sandbox`                                                               | —          |
| **IranKish**         | [Iran Kish](https://www.irankish.com)          | ایران کیش                    | `terminalId`, `password`, `acceptorId`, `pubKey`                                       | —          |
| **IsipaymentSamin**  | [Isipayment Samin](https://isipayment.ir)      | ایزایران ثمین                | `merchant_code`, `merchant_password`, `terminal_code`, `type`, `number_of_installment` | —          |
| **Keepa**            | [Keepa - Kipaa](https://keepa.ir)              | کیپا                         | `token`                                                                                | —          |
| **MehrIran**         | [MehrIran](https://qmb.ir)                     | بانک مهر ایران               | `terminal_id`, `merchant_nid`, `encrypt_key`                                           | —          |
| **NextPay**          | [NextPay](https://nextpay.org)                 | نکست پی                      | `api_key`                                                                              | —          |
| **Omidpay**          | [Omidpay - Sayan Card](https://omidpayment.ir) | امید پی (سایان کارت)         | `user_id`, `password`                                                                  | —          |
| **PardakhtNovin**    | [Pardakht Novin](https://pna.co.ir)            | پرداخت نوین                  | `userId`, `password`, `terminalId`                                                     | —          |
| **Payir**            | [Pay.ir](https://www.pay.ir)                   | پی.آی‌آر                      | `api`                                                                                  | —          |
| **PayPing**          | [PayPing](https://payping.ir)                  | پی پینگ                      | `token`                                                                                | —          |
| **PEC**              | [PEC](https://pec.ir)                          | تجارت الکترونیک پارسیان      | `login_account`                                                                        | —          |
| **PEP**              | [PEP](https://pep.co.ir)                       | پرداخت الکترونیک پاسارگاد    | `username`, `password`, `terminal_number`                                              | —          |
| **Polam**            | [Polam (Poolam)](https://polam.io)             | پولام                        | `api_key`                                                                              | —          |
| **RefahBeta**        | [Refah Beta](https://beta.refah-bank.ir)       | بانک رفاه بتا                | `client_id`, `client_secret`, `api_key`, `number_of_installments`                      | Bulk Check |
| **Sadad**            | [Sadad](https://sadadpsp.ir)                   | پرداخت الکترونیک سداد (ملی)  | `terminal_id`, `merchant_id`, `key`                                                    | —          |
| **SadadBNPL**        | [SadadBNPL](https://sadadpsp.ir)               | سداد BNPL                    | `terminal_id`, `merchant_id`, `key`                                                    | —          |
| **Sep**              | [Saman Electronic Payment](https://www.sep.ir) | پرداخت الکترونیک سامان (سپ)  | `terminalId`                                                                           | —          |
| **Sepal**            | [Sepal](https://sepal.ir)                      | سپال                         | `api_key`                                                                              | —          |
| **SepehrPay**        | [Sepehr Pay](https://www.sepehrpay.com)        | پرداخت الکترونیک سپهر (مبنا) | `terminalId`                                                                           | —          |
| **Shepa**            | [Shepa](https://shepa.com)                     | شپا                          | `api`                                                                                  | —          |
| **SnappPay**         | [SnappPay](https://snapppay.ir)                | اسنپ‌پی                       | `username`, `password`, `client_id`, `client_secret`                                   | —          |
| **TabaPay**          | [TabaPay](https://tabapay.ir)                  | تاباپی                       | `token`                                                                                | —          |
| **TejaratBajet**     | [Tejarat Bajet](https://mybajet.ir)            | بانک تجارت - باجت            | `client_id`, `client_secret`, `sandbox`                                                | Bulk Check |
| :test_tube: **Test** | **Test**                                       | **تست**                      | —                                                                                      | —          |
| **Vandar**           | [Vandar](https://vandar.io)                    | وندار                        | `api_key`                                                                              | —          |
| **ZarinPal**         | [Zarin Pal](https://www.zarinpal.com)          | زرین پال                     | `merchant_id`                                                                          | —          |
| **Zibal**            | [Zibal](https://zibal.ir)                      | زیبال                        | `merchant`                                                                             | —          |

> **Features**: `Bulk Check` = supports bulk transaction status checking.

اگر درگاه مورد نظر خود را پیدا نکردید، به ما اطلاع دهید یا در اضافه کردن آن مشارکت کنید.

---

## Usage | نحوه استفاده

The payment flow consists of 3 steps: **Request** (get token), **Redirect** (send user to gateway), and **Verify** (confirm payment).

فرایند پرداخت شامل ۳ مرحله است: **دریافت توکن**، **هدایت کاربر به درگاه**، و **تأیید پرداخت**.

### 1. Request Token | دریافت توکن

```php
use Farayaz\Larapay\Exceptions\LarapayException;
use Larapay;

$gateway = 'ZarinPal';
$config = [
    'merchant_id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
];

$amount = 10000; // Rial | ریال
$id = 1230; // Transaction ID | شماره تراکنش
$callbackUrl = route('payment.callback', $id);
$nationalId = '1234567890';
$mobile = '09131234567';

try {
    $result = Larapay::gateway($gateway, $config)
        ->request(
            id: $id,
            amount: $amount,
            callbackUrl: $callbackUrl,
            nationalId: $nationalId,
            mobile: $mobile,
        );
} catch (LarapayException $e) {
    // handle error
}

// Save token for later verification
$token = $result['token'];
$fee = $result['fee'];
```

**Response:**

| Key     | Type   | Description                             |
| ------- | ------ | --------------------------------------- |
| `token` | string | Gateway token to use in redirect/verify |
| `fee`   | int    | Transaction fee in Rials                |

### 2. Redirect to Gateway | هدایت به درگاه

```php
try {
    return Larapay::gateway($gateway, $config)
        ->redirect(id: $id, token: $token, callbackUrl: $callbackUrl);
} catch (LarapayException $e) {
    // handle error
}
```

This returns a redirect response (either a `RedirectResponse` to the gateway or a form view).

### 3. Verify Payment | تأیید پرداخت

```php
$params = $request->all();

try {
    $result = Larapay::gateway($gateway, $config)
        ->verify(
            id: $id,
            amount: $amount,
            nationalId: $nationalId,
            mobile: $mobile,
            token: $token,
            params: $params,
        );
} catch (LarapayException $e) {
    // Transaction failed
}

// Transaction successful
$result['result'];       // Status message
$result['reference_id']; // Gateway reference ID
$result['tracking_code'];// Tracking code
$result['card'];         // Masked card number
$result['fee'];          // Transaction fee
```

**Response:**

| Key             | Type         | Description                              |
| --------------- | ------------ | ---------------------------------------- |
| `result`        | string       | Status message from gateway              |
| `reference_id`  | string\|null | Gateway reference number (شناسه پیگیری)  |
| `tracking_code` | string\|null | Transaction tracking code (شماره تراکنش) |
| `card`          | string\|null | Masked card number (شماره کارت)          |
| `fee`           | int          | Transaction fee in Rials                 |

---

### Complete Controller Example | مثال کامل کنترلر

```php
<?php

namespace App\Http\Controllers;

use Farayaz\Larapay\Exceptions\LarapayException;
use Illuminate\Http\Request;
use Larapay;

class PaymentController extends Controller
{
    protected string $gateway = 'ZarinPal';
    protected array $config = [
        'merchant_id' => env('ZARINPAL_MERCHANT_ID'),
    ];

    public function pay()
    {
        $amount = 100000; // Rial
        $id = rand(1000, 9999); // Unique transaction ID
        $callbackUrl = route('payment.callback', $id);
        $nationalId = '1234567890';
        $mobile = '09131234567';

        try {
            $result = Larapay::gateway($this->gateway, $this->config)
                ->request(
                    id: $id,
                    amount: $amount,
                    callbackUrl: $callbackUrl,
                    nationalId: $nationalId,
                    mobile: $mobile,
                );
        } catch (LarapayException $e) {
            return back()->with('error', $e->getMessage());
        }

        // Store $result['token'], $result['fee'], $id in session/db
        session(['payment_token' => $result['token'], 'payment_id' => $id, 'amount' => $amount]);

        return Larapay::gateway($this->gateway, $this->config)
            ->redirect($id, $result['token'], $callbackUrl);
    }

    public function callback(Request $request, int $id)
    {
        $token = session('payment_token');
        $amount = session('amount');

        try {
            $result = Larapay::gateway($this->gateway, $this->config)
                ->verify(
                    id: $id,
                    amount: $amount,
                    nationalId: '1234567890',
                    mobile: '09131234567',
                    token: $token,
                    params: $request->all(),
                );
        } catch (LarapayException $e) {
            return view('payment.failed', ['message' => $e->getMessage()]);
        }

        return view('payment.success', [
            'tracking_code' => $result['tracking_code'],
            'reference_id' => $result['reference_id'],
            'card' => $result['card'],
        ]);
    }
}
```

---

## Advanced Usage | استفاده پیشرفته

### Refund | بازگشت وجه

Only available on gateways that implement `RefundableInterface`.

فقط در درگاه‌هایی که `RefundableInterface` را پیاده‌سازی کرده‌اند در دسترس است.

```php
try {
    $result = Larapay::gateway($gateway, $config)
        ->refund(
            id: $transactionId,
            amount: $amount,
            referenceId: $referenceId,
            reason: 'Customer request',
        );
} catch (LarapayException $e) {
    // Refund failed
}
```

### Bulk Check | بررسی گروهی

Only available on gateways that implement `BulkCheckableInterface`.

فقط در درگاه‌هایی که `BulkCheckableInterface` را پیاده‌سازی کرده‌اند در دسترس است.

```php
try {
    Larapay::gateway($gateway, $config)
        ->bulkCheck(
            successCallback: function ($id, $data) {
                // Transaction $id is successful
                // $data contains: result, card, tracking_code, reference_id, amount, fee
            },
            unsuccessCallback: function ($id) {
                // Transaction $id failed
            },
        );
} catch (LarapayException $e) {
    // handle error
}
```

### Check Gateway Capabilities | بررسی قابلیت‌های درگاه

```php
$gateway = Larapay::gateway($gateway, $config);

if ($gateway->supports('refund')) {
    // Gateway supports refund
}

if ($gateway->supports('bulk_check')) {
    // Gateway supports bulk check
}
```

---

## Error Handling | مدیریت خطا

All exceptions are thrown as `Farayaz\Larapay\Exceptions\LarapayException`.

تمام خطاها به صورت `LarapayException` پرتاب می‌شوند.

```php
use Farayaz\Larapay\Exceptions\LarapayException;

try {
    // ...
} catch (LarapayException $e) {
    $message = $e->getMessage(); // Persian or English error message
    $code = $e->getCode();       // Optional error code
}
```

---

## Benefits | مزایا

- **Simple** | ساده: Unified API for all gateways | API یکسان برای همه درگاه‌ها
- **Flexible** | انعطاف‌پذیر: Easy to add new gateways | اضافه کردن درگاه جدید آسان
- **Fee Calculation** | محاسبه هزینه: Built-in transaction fee calculation | محاسبه خودکار کارمزد
- **Maintained** | به‌روز: Actively maintained and updated | نگهداری و به‌روزرسانی فعال
- **Open Source** | متن‌باز: MIT license | مجوز MIT

---

## Contributing | مشارکت

If you don't find the gateway you want, feel free to contribute!

اگر درگاه مورد نظر خود را پیدا نکردید، در اضافه کردن آن مشارکت کنید!

1. Create a new class in `src/Gateways/` extending `GatewayAbstract`
2. Implement `request()`, `redirect()`, and `verify()` methods
3. Optionally implement `RefundableInterface` or `BulkCheckableInterface`
4. Add the gateway to the table in this README
5. Submit a Pull Request

---

## License | مجوز

This package is open-source software licensed under the [MIT license](LICENSE).

این پکیج تحت مجوز MIT منتشر شده است.
