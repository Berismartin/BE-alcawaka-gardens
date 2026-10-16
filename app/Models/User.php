<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Domains\Users\User\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

// role is deliberately not fillable: it is set only by trusted code (seeders, staff management).
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'invitation_token', 'pin_hash'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $attributes = ['role' => 'guest', 'is_active' => true];

    /** An invited staff member who has not set a password yet. */
    public function isInvitePending(): bool
    {
        return $this->invitation_token !== null;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'preferences' => 'array',
            'is_active' => 'boolean',
            'last_active_at' => 'datetime',
            'invited_at' => 'datetime',
            'pin_locked_until' => 'datetime',
        ];
    }
}
