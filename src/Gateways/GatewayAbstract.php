<?php

namespace Farayaz\Larapay\Gateways;

use Farayaz\Larapay\Exceptions\GatewayException;
use Illuminate\Support\Arr;

abstract class GatewayAbstract
{
    protected $statuses = [];

    protected $requirements = [];

    function __construct(protected array $config = [])
    {
        $this->requirements();
    }

    abstract function request($id, $amount, $callback);

    abstract function verify($id, $amount, $token, array $params = []);

    abstract function redirect($id, $token);

    protected function requirements()
    {
        if (!Arr::has($this->config, $this->requirements)) {
            throw new GatewayException(implode(', ', $this->requirements) . ' is required.');
        }
    }

    protected function translateStatus($code)
    {
        return $this->statuses[$code] ?? $code;
    }

    public function fee($amount)
    {
        $fee = 1_200;
        if ($amount >= 6_000_000) {
            // TODO check round
            $fee = min(40_000, round($amount * 0.0002));
        }
        return $fee;
    }
}
