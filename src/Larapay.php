<?php

namespace Farayaz\Larapay;

use Farayaz\Larapay\Exceptions\GatewayException;

final class Larapay
{
    protected $gateway;

    function gateway(string $gateway, array $config)
    {
        $gatewayClass = __NAMESPACE__ . '\Gateways\\' . $gateway;
        if (!class_exists($gatewayClass)) {
            throw new GatewayException('gateway "' . $gateway . '" doesnt exists');
        }
        $this->gateway = new $gatewayClass($config);

        return $this;
    }

    function request(int $id, int $amount, string $callback)
    {
        $this->_check();

        return $this->gateway->request($id, $amount, $callback);
    }

    function verify(int $id, int $amount, string $token, array $params = [])
    {
        $this->_check();

        return $this->gateway->verify($id, $amount, $token, $params);
    }

    function redirect(int $id, string $token)
    {
        $this->_check();

        return $this->gateway->redirect($id, $token);
    }

    private function _check()
    {
        if (empty($this->gateway)) {
            throw new GatewayException(__METHOD__ . __LINE__);
        }
    }
}
