<?php

namespace Farayaz\Larapay\Enums;

enum TransactionStatus: string
{
    case Success = 'success';
    case Failed = 'failed';
    case Pending = 'pending';
    case Refunded = 'refunded';
    case RefundFailed = 'refund_failed';
    case Unknown = 'unknown';
}
