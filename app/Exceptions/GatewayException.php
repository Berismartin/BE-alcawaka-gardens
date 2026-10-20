<?php

namespace App\Exceptions;

/** The payment gateway could not be reached or refused the request. Nothing was charged by us. */
class GatewayException extends DomainException
{
    protected int $status = 502;
}
