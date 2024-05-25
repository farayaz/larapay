<?php

namespace Farayaz\Larapay\Gateways;

use Farayaz\Larapay\Exceptions\LarapayException;
use Illuminate\Support\Arr;

abstract class GatewayAbstract
{
    protected $statuses = [];

    protected $requirements = [];

    public function __construct(protected array $config = [])
    {
        $this->requirements();
    }

    abstract public function request(
        int $id,
        int $amount,
        string $nationalId,
        string $mobile,
        string $callbackUrl
    ): array;

    abstract public function verify(
        int $id,
        int $amount,
        string $nationalId,
        string $mobile,
        string $token,
        array $params
    ): array;

    abstract public function redirect(int $id, string $token, string $callbackUrl);

    protected function requirements()
    {
        if (! Arr::has($this->config, $this->requirements)) {
            throw new LarapayException(implode(', ', $this->requirements) . ' is required.');
        }
    }

    protected function translateStatus(int|string|null $code = null)
    {
        $this->statuses['id-mismatch'] = 'عدم تطبیق شناسه بازگشتی';
        $this->statuses['amount-mismatch'] = 'عدم تطبیق مبلغ بازگشتی';
        $this->statuses['token-mismatch'] = 'عدم تطبیق توکن بازگشتی';
        $this->statuses['connection-exception'] = 'خطا ارتباطی با سرویس دهنده';

        return $this->statuses[$code] ?? $code ?? '<null>';
    }

    public function fee(int $amount): int
    {
        $fee = 1_200;
        if ($amount >= 6_000_000) {
            // TODO check round
            $fee = min(40_000, round($amount * 0.0002));
        }

        return $fee;
    }
}
