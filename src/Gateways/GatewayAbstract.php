<?php

namespace Farayaz\Larapay\Gateways;

use Farayaz\Larapay\Exceptions\GatewayException;
use Illuminate\Support\Arr;

abstract class GatewayAbstract
{
    protected $statuses = [];

    protected $requirements = [];

    public function __construct(protected array $config = [])
    {
        $this->requirements();
    }

    abstract public function request(int $id, int $amount, string $callback): array;

    abstract public function verify(int $id, int $amount, string $token, array $params = []): array;

    abstract public function redirect(int $id, string $token);

    protected function requirements()
    {
        if (! Arr::has($this->config, $this->requirements)) {
            throw new GatewayException(implode(', ', $this->requirements) . ' is required.');
        }
    }

    protected function translateStatus(int|string $code)
    {
        return $this->statuses[$code] ?? $code;
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
