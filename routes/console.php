<?php

use App\Domains\Channels\Channel\Services\ChannelService;
use App\Domains\Facilities\FacilityBooking\Services\FacilityBookingService;
use App\Domains\Reservations\Reservation\Services\ReservationService;
use App\Domains\Users\User\Enums\UserRole;
use App\Models\IdempotencyKey;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// First real login on a fresh server (the seeder only makes demo logins locally). Invite everyone else from the portal.
Artisan::command('staff:create-manager {email} {name}', function (string $email, string $name) {
    $password = $this->secret('Password (at least 10 characters)');
    $validator = Validator::make(compact('email', 'name', 'password'), [
        'email' => ['required', 'email', 'max:160', 'unique:users,email'],
        'name' => ['required', 'string', 'max:120'],
        'password' => ['required', Password::min(10)->letters()->numbers()],
    ]);
    if ($validator->fails()) {
        foreach ($validator->errors()->all() as $error) {
            $this->error($error);
        }

        return 1;
    }
    $user = new User(compact('email', 'name', 'password'));
    $user->forceFill(['role' => UserRole::Manager])->save();
    $this->info("Manager {$email} created. Sign in at /portal/login.");

    return 0;
})->purpose('Create a manager account for the staff portal');

Schedule::command('model:prune', ['--model' => [IdempotencyKey::class]])->daily();

// Unpaid website holds give their room back.
Schedule::call(fn () => app(ReservationService::class)->expireHolds())
    ->everyMinute()->name('reservations:expire-holds')->withoutOverlapping();

// Unpaid website facility bookings are cancelled when their hold runs out.
Schedule::call(fn () => app(FacilityBookingService::class)->expireHolds())
    ->everyMinute()->name('facility-bookings:expire-holds')->withoutOverlapping();

// Pull OTA calendar feeds so their bookings block our rooms.
Schedule::call(fn () => app(ChannelService::class)->syncAll())
    ->everyFifteenMinutes()->name('channels:sync')->withoutOverlapping();
