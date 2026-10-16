<?php

namespace App\Models\Pos;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'secret_hash', 'created_by', 'last_used_at'])]
#[Hidden(['secret_hash'])]
class PosTerminal extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime'];
    }
}
