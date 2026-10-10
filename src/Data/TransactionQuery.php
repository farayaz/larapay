<?php

namespace Farayaz\Larapay\Data;

use DateTimeInterface;
use Farayaz\Larapay\Enums\TransactionStatus;
use InvalidArgumentException;

final readonly class TransactionQuery
{
    public function __construct(
        public int $page = 1,
        public int $size = 50,
        public ?DateTimeInterface $fromDate = null,
        public ?DateTimeInterface $toDate = null,
        public ?int $amount = null,
        public ?string $nationalId = null,
        public ?string $cardNumber = null,
        public TransactionStatus|string|null $status = null,
    ) {
        if ($page < 1) {
            throw new InvalidArgumentException('Transaction page must be at least 1.');
        }

        if ($size < 1) {
            throw new InvalidArgumentException('Transaction page size must be at least 1.');
        }

        if ($fromDate !== null && $toDate !== null && $fromDate > $toDate) {
            throw new InvalidArgumentException('Transaction from date must be before or equal to the to date.');
        }
    }

    public function forPage(int $page): self
    {
        return new self(
            page: $page,
            size: $this->size,
            fromDate: $this->fromDate,
            toDate: $this->toDate,
            amount: $this->amount,
            nationalId: $this->nationalId,
            cardNumber: $this->cardNumber,
            status: $this->status,
        );
    }
}
