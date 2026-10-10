<?php

namespace Farayaz\Larapay\Interfaces;

use Farayaz\Larapay\Data\TransactionListResult;
use Farayaz\Larapay\Data\TransactionQuery;
use Farayaz\Larapay\Enums\TransactionFilter;

interface TransactionListableInterface
{
    public function listTransactions(TransactionQuery $query): TransactionListResult;

    /**
     * @return list<TransactionFilter>
     */
    public function supportedTransactionFilters(): array;
}
