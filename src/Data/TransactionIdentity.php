<?php

namespace Farayaz\Larapay\Data;

use InvalidArgumentException;

final readonly class TransactionIdentity
{
    public function __construct(
        public int|string|null $id = null,
        public ?string $token = null,
        public ?string $referenceId = null,
    ) {
        if ($id === null && $token === null && $referenceId === null) {
            throw new InvalidArgumentException('Transaction identity must contain at least one identifier.');
        }
    }
}
