<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

#[Fillable(['user_id', 'key', 'fingerprint', 'status', 'body'])]
class IdempotencyKey extends Model
{
    use Prunable;

    /** Keys are only needed while a client may still retry. */
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDay());
    }
}
