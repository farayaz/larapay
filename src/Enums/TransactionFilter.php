<?php

namespace Farayaz\Larapay\Enums;

enum TransactionFilter: string
{
    case DateRange = 'date_range';
    case NationalId = 'national_id';
    case CardNumber = 'card_number';
    case Amount = 'amount';
    case Status = 'status';
}
