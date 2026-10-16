<?php

namespace App\Exceptions;

/** 409: the request was valid, but the world changed underneath it (e.g. a room just got booked). */
class ConflictException extends DomainException
{
    protected int $status = 409;
}
