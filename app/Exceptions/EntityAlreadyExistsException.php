<?php

namespace App\Exceptions;

class EntityAlreadyExistsException extends DomainException
{
    protected int $status = 409;

    public function __construct(string $entity, string $value)
    {
        parent::__construct("{$entity} already exists: {$value}");
    }
}
