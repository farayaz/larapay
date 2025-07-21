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

    /**
     * Check if gateway supports a specific capability
     */
    public function supports(string $capability): bool
    {
        $this->_check();

        return $this->gateway->supports($capability);
    }

    /**
     * Refund a transaction (only for supported gateways)
     */
    public function refund(int $id, int $amount, string $referenceId, string $reason = '')
    {
        $this->_check();

        if (! $this->gateway->supportsRefund()) {
            throw new LarapayException('Gateway does not support refunds');
        }

        return $this->gateway->refund($id, $amount, $referenceId, $reason);
    }

    /**
     * Bulk check transaction statuses (only for supported gateways)
     */
    public function bulkCheck(callable $successCallback, callable $unsuccessCallback): void
    {
        $this->_check();

        if (! $this->gateway->supportsBulkCheck()) {
            throw new LarapayException('Gateway does not support bulk check');
        }

        $this->gateway->bulkCheck($successCallback, $unsuccessCallback);
    }

    private function _check()
    {
        if (empty($this->gateway)) {
            throw new LarapayException(__METHOD__ . __LINE__);
        }
    }
}
