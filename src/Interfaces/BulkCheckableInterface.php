<?php

namespace Farayaz\Larapay\Interfaces;

interface BulkCheckableInterface
{
    /**
     * Check the status of multiple transactions.
     * Callbacks receive a TransactionIdentity as their first argument.
     */
    public function bulkCheck(callable $successCallback, callable $unsuccessCallback): void;
}
