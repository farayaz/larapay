<?php

namespace Farayaz\Larapay;

use Farayaz\Larapay\Exceptions\LarapayException;

class Larapay
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

    public function request(
        int $id,
        int $amount,
        string $nationalId,
        string $mobile,
        string $callbackUrl
    ) {
        $this->_check();

        return $this->gateway->request($id, $amount, $nationalId, $mobile, $callbackUrl);
    }

    public function verify(
        int $id,
        int $amount,
        string $nationalId,
        string $mobile,
        string $token,
        array $params
    ) {
        $this->_check();

        return $this->gateway->verify($id, $amount, $nationalId, $mobile, $token, $params);
    }

    public function redirect(int $id, string $token, string $callbackUrl)
    {
        $this->_check();

        return $this->gateway->redirect($id, $token, $callbackUrl);
    }

    private function _check()
    {
        if (empty($this->gateway)) {
            throw new LarapayException(__METHOD__ . __LINE__);
        }
    }
}
