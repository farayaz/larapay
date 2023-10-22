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

    abstract function request($id, $amount, $callback);

    abstract function verify($id, $amount, $token, array $params = []);

    abstract function redirect($id, $token);
}
