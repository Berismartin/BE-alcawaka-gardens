<?php

namespace App\Domains\Payments\Gateway\Enums;

/** Where an order stands at the provider. Pesapal status_code: 0 invalid, 1 completed, 2 failed, 3 reversed. */
enum GatewayState: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
    case Invalid = 'invalid';
    case Reversed = 'reversed';

    /** Final answers: no point asking again. */
    public function isFinal(): bool
    {
        return $this !== self::Pending;
    }
}
