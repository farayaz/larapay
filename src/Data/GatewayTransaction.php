<?php

namespace Farayaz\Larapay\Data;

use DateTimeImmutable;
use Farayaz\Larapay\Enums\TransactionStatus;

final readonly class GatewayTransaction
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public TransactionIdentity $identity,
        public TransactionStatus $status,
        public ?int $amount,
        public string $rawStatus,
        public ?string $nationalId = null,
        public ?DateTimeImmutable $createdAt = null,
        public array $raw = [],
    ) {}
}
