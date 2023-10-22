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

    function request($id, $amount, $callback)
    {
        $this->_check();

        $this->gateway->request($id, $amount, $callback);

        return $this;
    }

    function verify($id, $amount, $token, array $params = [])
    {
        $this->_check();

        $this->gateway->verify($id, $amount, $token, $params);

        return $this;
    }

    function redirect($id, $token)
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
