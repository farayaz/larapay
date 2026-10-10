<?php

namespace Farayaz\Larapay\Data;

final readonly class TransactionListResult
{
    /**
     * @param  list<GatewayTransaction>  $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $size,
    ) {}
}
