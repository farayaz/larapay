<?php

namespace Farayaz\Larapay;

use Farayaz\Larapay\Exceptions\LarapayException;

final class Larapay
{
    protected $gateway;

    public function gateway(string $gateway, array $config)
    {
        $gatewayClass = __NAMESPACE__ . '\Gateways\\' . $gateway;
        if (! class_exists($gatewayClass)) {
            throw new LarapayException('gateway "' . $gateway . '" doesnt exists');
        }
        $this->gateway = new $gatewayClass($config);

        return $this;
    }

    public function request(int $id, int $amount, string $callback)
    {
        $this->_check();

        return $this->gateway->request($id, $amount, $callback);
    }

    public function verify(int $id, int $amount, string $token, array $params = [])
    {
        $this->_check();

        return $this->gateway->verify($id, $amount, $token, $params);
    }

    public function redirect(int $id, string $token)
    {
        $this->_check();

        return $this->gateway->redirect($id, $token);
    }

    private function _check()
    {
        if (empty($this->gateway)) {
            throw new LarapayException(__METHOD__ . __LINE__);
        }
    }
}
