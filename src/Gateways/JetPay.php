<?php

namespace Farayaz\Larapay\Gateways;

use DateTimeImmutable;
use Farayaz\Larapay\Data\GatewayTransaction;
use Farayaz\Larapay\Data\TransactionIdentity;
use Farayaz\Larapay\Data\TransactionListResult;
use Farayaz\Larapay\Data\TransactionQuery;
use Farayaz\Larapay\Enums\TransactionFilter;
use Farayaz\Larapay\Enums\TransactionStatus;
use Farayaz\Larapay\Exceptions\LarapayException;
use Farayaz\Larapay\Interfaces\TransactionListableInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\View;
use Throwable;

class JetPay extends GatewayAbstract implements TransactionListableInterface
{
    private const BASE_URL = 'https://jetpay.mybajet.ir/api/v1/credit-consumption/';

    private const INQUIRY_ATTEMPTS = 4;

    private const INQUIRY_DELAY_MICROSECONDS = 1_500_000;

    private const ORDER_NOT_FOUND = 700003;

    protected $requirements = [
        'username',
        'password',
        'terminal_id',
        'credit_id',
    ];

    public function request(
        int $id,
        int $amount,
        string $nationalId,
        string $mobile,
        string $callbackUrl,
        array $allowedCards = []
    ): array {
        $balance = $this->requestApi('get', 'account-balance', [
            'nationalCode' => $nationalId,
        ]);

        $requestId = $this->requiredString($balance['requestId'] ?? null, 'شناسه درخواست دریافت نشد');
        $creditId = (string) $this->config['credit_id'];
        $credits = collect($balance['credits'] ?? []);
        $credit = $credits->first(
            fn ($credit) => (string) ($credit['credit_info']['credit_id'] ?? '') === $creditId
        );

        if (! $credit) {
            throw new LarapayException('جت‌پی: اعتبار تنظیم‌شده یافت نشد.');
        }

        if (($credit['credit_balance'] ?? 0) < $amount) {
            throw new LarapayException(
                'جت‌پی: عدم موجودی کافی. موجودی: ' . number_format($credit['credit_balance'] ?? 0) . ' ریال'
            );
        }

        $this->requestApi('post', 'send-otp', [
            'nationalCode' => $nationalId,
            'requestId' => $requestId,
            'creditId' => $creditId,
            'amount' => $amount,
        ]);

        return [
            'token' => $requestId,
            'fee' => 0,
        ];
    }

    public function redirect(int $id, string $token, string $callbackUrl)
    {
        return View::make('larapay::otp', compact('callbackUrl'));
    }

    public function verify(
        int $id,
        int $amount,
        string $nationalId,
        string $mobile,
        string $token,
        array $params
    ): array {
        $requestId = $this->requiredString($token, 'شناسه درخواست دریافت نشد');
        $creditId = (string) $this->config['credit_id'];
        $otp = $this->requiredString($params['otp'] ?? null, 'کد یکبار مصرف الزامی است');

        $purchase = $this->requestApi('post', 'purchase', [
            'nationalCode' => $nationalId,
            'amount' => $amount,
            'requestId' => $requestId,
            'creditId' => $creditId,
            'otp' => $otp,
        ]);
        $referenceNumber = $this->requiredString(
            $purchase['referenceNumber'] ?? null,
            'شماره مرجع تراکنش دریافت نشد'
        );

        $this->waitForStatus($referenceNumber, 'PURCHASE', 'COMPLETED');

        $this->requestApi('post', 'confirm', [
            'referenceNumber' => $referenceNumber,
        ]);

        $this->waitForStatus($referenceNumber, 'CONFIRM', 'CONFIRMED');

        return [
            'result' => 'CONFIRMED',
            'card' => null,
            'tracking_code' => $referenceNumber,
            'reference_id' => $referenceNumber,
            'fee' => 0,
        ];
    }

    public function listTransactions(TransactionQuery $query): TransactionListResult
    {
        $this->validateTransactionQuery($query);

        $result = $this->requestApi('get', 'transaction-list', array_filter([
            'page' => $query->page,
            'size' => $query->size,
            'fromDate' => $query->fromDate?->format('Y-m-d'),
            'toDate' => $query->toDate?->format('Y-m-d'),
            'totalAmount' => $query->amount,
            'nationalCode' => $query->nationalId,
        ], fn (mixed $value): bool => $value !== null && $value !== ''));

        $items = [];
        $list = is_array($result['list'] ?? null) ? $result['list'] : [];
        foreach ($list as $item) {
            if (! is_array($item)) {
                continue;
            }

            $requestId = $this->requiredString($item['requestId'] ?? null, 'شناسه درخواست تراکنش دریافت نشد');
            $referenceNumber = $this->nullableString($item['referenceNumber'] ?? null);
            $rawStatus = $this->nullableString($item['status'] ?? null) ?? '';

            $items[] = new GatewayTransaction(
                identity: new TransactionIdentity(
                    token: $requestId,
                    referenceId: $referenceNumber,
                ),
                status: $this->normalizeTransactionStatus($rawStatus),
                amount: is_numeric($item['totalAmount'] ?? null) ? (int) $item['totalAmount'] : null,
                rawStatus: $rawStatus,
                nationalId: $this->nullableString($item['nationalCode'] ?? null),
                createdAt: $this->parseDate($item['createdAt'] ?? null),
                raw: $item,
            );
        }

        return new TransactionListResult(
            items: $items,
            total: is_numeric($result['total'] ?? null) ? (int) $result['total'] : count($items),
            page: $query->page,
            size: $query->size,
        );
    }

    /**
     * @return list<TransactionFilter>
     */
    public function supportedTransactionFilters(): array
    {
        return [
            TransactionFilter::DateRange,
            TransactionFilter::NationalId,
            TransactionFilter::Amount,
            // TODO: Re-enable when JetPay fixes transaction status filtering.
        ];
    }

    private function validateTransactionQuery(TransactionQuery $query): void
    {
        if ($query->cardNumber !== null) {
            throw new LarapayException('جت‌پی: جستجوی تراکنش بر اساس شماره کارت پشتیبانی نمی‌شود.');
        }

        if ($query->status !== null) {
            throw new LarapayException('جت‌پی: فیلتر وضعیت تراکنش موقتاً در دسترس نیست.');
        }

        if (
            $query->fromDate !== null
            && $query->toDate !== null
            && ($query->toDate->getTimestamp() - $query->fromDate->getTimestamp()) > (31 * 86_400)
        ) {
            throw new LarapayException('جت‌پی: بازه دریافت لیست تراکنش‌ها نمی‌تواند بیشتر از یک ماه باشد.');
        }
    }

    private function normalizeTransactionStatus(string $status): TransactionStatus
    {
        return match (strtoupper(trim($status))) {
            'SUCCESS', 'CONFIRMED', 'COMPLETED', 'تایید شده', 'تأیید شده', 'موفق' => TransactionStatus::Success,
            'FAILED', 'ناموفق' => TransactionStatus::Failed,
            'PROCESSING', 'PENDING', 'RETRY', 'در حال پردازش' => TransactionStatus::Pending,
            'REFUND_SUCCESS', 'REFUNDED', 'مسترد شده' => TransactionStatus::Refunded,
            'REFUND_FAILED', 'استرداد ناموفق' => TransactionStatus::RefundFailed,
            default => TransactionStatus::Unknown,
        };
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function parseDate(mixed $value): ?DateTimeImmutable
    {
        $value = $this->nullableString($value);
        if ($value === null) {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Throwable) {
            return null;
        }
    }

    private function waitForStatus(string $referenceNumber, string $step, string $expectedStatus): void
    {
        for ($attempt = 0; $attempt < self::INQUIRY_ATTEMPTS; $attempt++) {
            try {
                $inquiry = $this->requestApi('get', 'inquiry', [
                    'referenceNumber' => $referenceNumber,
                ], timeout: 3);
                $item = collect($inquiry)
                    ->flatten(1)
                    ->last(fn ($item) => is_array($item) && ($item['step'] ?? null) === $step);
            } catch (LarapayException $exception) {
                if ($exception->getCode() !== self::ORDER_NOT_FOUND || $attempt + 1 >= self::INQUIRY_ATTEMPTS) {
                    throw $exception;
                }

                $item = null;
            }

            $status = $item['status'] ?? null;
            if ($status === $expectedStatus) {
                return;
            }

            if ($status === 'FAILED') {
                throw new LarapayException("جت‌پی: عملیات {$step} ناموفق بود");
            }

            if ($attempt + 1 < self::INQUIRY_ATTEMPTS) {
                usleep(self::INQUIRY_DELAY_MICROSECONDS);
            }
        }

        throw new LarapayException("جت‌پی: عملیات {$step} هنوز نهایی نشده است");
    }

    private function authenticate(): string
    {
        $cacheKey = $this->authTokenCacheKey();
        $cachedToken = Cache::get($cacheKey);

        if (is_string($cachedToken) && $cachedToken !== '') {
            return $cachedToken;
        }

        $result = $this->requestApi('post', 'token', [
            'username' => $this->config['username'],
            'password' => $this->config['password'],
            'terminalId' => $this->config['terminal_id'],
        ], authenticated: false);
        $token = $this->requiredString($result['token'] ?? null, 'توکن احراز هویت دریافت نشد');

        $tokenTtl = max(300, (int) ($result['expiresIn'] ?? 0) - 60);

        Cache::put($cacheKey, $token, $tokenTtl);

        return $token;
    }

    private function requestApi(
        string $method,
        string $endpoint,
        array $data = [],
        int $timeout = 5,
        bool $authenticated = true,
        bool $retryOnUnauthorized = true
    ): array {
        $headers = $authenticated ? ['Authorization' => 'Bearer ' . $this->authenticate()] : [];

        try {
            $response = Http::timeout($timeout)
                ->asJson()
                ->acceptJson()
                ->withHeaders($headers)
                ->$method(self::BASE_URL . $endpoint, $data);

            if ($authenticated && $retryOnUnauthorized && $response->status() === 401) {
                Cache::forget($this->authTokenCacheKey());

                return $this->requestApi(
                    $method,
                    $endpoint,
                    $data,
                    $timeout,
                    authenticated: true,
                    retryOnUnauthorized: false
                );
            }

            $response->throw();
            $payload = $response->json();

            if (! is_array($payload) || ($payload['success'] ?? false) !== true) {
                $this->throwApiError($payload);
            }

            $result = $payload['result'] ?? [];
            if ($result === null) {
                return [];
            }

            if (! is_array($result)) {
                $this->throwApiError($payload, 'پاسخ نامعتبر از سرویس دریافت شد');
            }

            return $result;
        } catch (RequestException $exception) {
            $this->throwApiError($exception->response->json(), $exception->getMessage());
        } catch (ConnectionException $exception) {
            throw new LarapayException(
                'جت‌پی: ' . $this->translateStatus('connection-exception'),
                LarapayException::CODE_CONNECTION_EXCEPTION
            );
        }
    }

    private function authTokenCacheKey(): string
    {
        return __CLASS__ . ':auth:' . sha1(implode('|', [
            $this->config['username'],
            $this->config['terminal_id'],
            hash('sha256', $this->config['password']),
            self::BASE_URL,
        ]));
    }

    private function requiredString(mixed $value, string $message): string
    {
        if (empty($value) || (! is_string($value) && ! is_int($value))) {
            throw new LarapayException('جت‌پی: ' . $message);
        }

        return (string) $value;
    }

    private function throwApiError(mixed $response, ?string $fallback = null): never
    {
        $payload = is_array($response) ? $response : [];
        $result = is_array($payload['result'] ?? null) ? $payload['result'] : [];
        $error = is_array($result['error'] ?? null) ? $result['error'] : [];
        $message = $error['fa'] ?? $error['en'] ?? $fallback ?? 'خطای نامشخص';

        $code = is_numeric($error['code'] ?? null) ? (int) $error['code'] : 0;

        throw new LarapayException('جت‌پی: ' . $message, $code);
    }
}
