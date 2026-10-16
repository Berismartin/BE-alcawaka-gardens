<?php

namespace App\Exceptions;

class EntityNotFoundException extends DomainException
{
    protected int $status = 404;

    public function __construct(string $entity, string $id)
    {
        parent::__construct("{$entity} not found: {$id}");
    }
}
