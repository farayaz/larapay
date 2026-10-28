<?php

namespace Farayaz\Larapay;

use Farayaz\Larapay\Data\TransactionListResult;
use Farayaz\Larapay\Data\TransactionQuery;
use Farayaz\Larapay\Enums\TransactionFilter;
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
        int|string $id,
        int $amount,
        string $nationalId,
        string $mobile,
        string $callbackUrl,
        array $allowedCards = []
    ) {
        $this->_check();

        return $this->gateway->request($id, $amount, $nationalId, $mobile, $callbackUrl, $allowedCards);
    }

    public function verify(
        int|string $id,
        int $amount,
        string $nationalId,
        string $mobile,
        string $token,
        array $params
    ) {
        $this->_check();

        return $this->gateway->verify($id, $amount, $nationalId, $mobile, $token, $params);
    }

    public function redirect(int|string $id, string $token, string $callbackUrl)
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

    /**
     * Get a paginated transaction list from a supported gateway.
     */
    public function listTransactions(?TransactionQuery $query = null): TransactionListResult
    {
        $this->_check();

        if (! $this->gateway->supportsTransactionList()) {
            throw new LarapayException('Gateway does not support transaction lists');
        }

        return $this->gateway->listTransactions($query ?? new TransactionQuery);
    }

    /**
     * Get the transaction filters supported by the selected gateway.
     *
     * @return list<TransactionFilter>
     */
    public function supportedTransactionFilters(): array
    {
        $this->_check();

        if (! $this->gateway->supportsTransactionList()) {
            return [];
        }

        return $this->gateway->supportedTransactionFilters();
    }

    private function _check()
    {
        if (empty($this->gateway)) {
            throw new LarapayException('Gateway not selected. Call gateway() first.');
        }
    }
}
