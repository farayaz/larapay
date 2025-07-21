<?php

namespace Farayaz\Larapay\Interfaces;

interface RefundableInterface
{
    /**
     * Refund a transaction
     *
     * @param  int  $id  Transaction ID
     * @param  int  $amount  Amount to refund
     * @param  string  $referenceId  Gateway reference ID
     * @param  string  $reason  Refund reason
     */
    public function refund(int $id, int $amount, string $referenceId, string $reason = ''): array;
}
