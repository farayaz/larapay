<?php

namespace Farayaz\Larapay\Interfaces;

interface BulkCheckableInterface
{
    /**
     * Check the status of multiple transactions
     */
    public function bulkCheck(callable $successCallback, callable $unsuccessCallback): void;
}
