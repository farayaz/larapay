<?php

namespace Farayaz\Larapay\Enums;

enum TransactionStatus: string
{
    /** The payment completed successfully. */
    case Completed = 'completed';

    /** The payment attempt failed or was declined. */
    case Failed = 'failed';

    /** The final payment result is not available yet. */
    case Pending = 'pending';

    /** The full payment amount was refunded. */
    case Refunded = 'refunded';

    /** The provider status could not be mapped. */
    case Unknown = 'unknown';
}
