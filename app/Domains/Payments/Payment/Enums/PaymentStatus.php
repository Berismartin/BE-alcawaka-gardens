<?php

namespace App\Domains\Payments\Payment\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
