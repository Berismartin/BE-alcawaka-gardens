<?php

namespace App\Exceptions;

class EntityValidationException extends DomainException
{
    protected int $status = 422;
}
