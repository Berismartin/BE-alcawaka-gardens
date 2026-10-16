<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Base for every business-rule failure. Rendered as
 * {"success": false, "message": ..., "errors": ...} in bootstrap/app.php.
 */
class DomainException extends RuntimeException
{
    protected int $status = 400;

    /** @param array<string, array<int, string>> $errors */
    public function __construct(string $message, protected array $errors = [])
    {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->status;
    }

    /** @return array<string, array<int, string>> */
    public function errors(): array
    {
        return $this->errors;
    }
}
