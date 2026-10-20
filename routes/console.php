<?php

use App\Domains\Channels\Channel\Services\ChannelService;
use App\Domains\Facilities\FacilityBooking\Services\FacilityBookingService;
use App\Domains\Payments\Gateway\Pesapal\PesapalGateway;
use App\Domains\Payments\Payment\Services\OnlinePaymentService;
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

// Online payments whose IPN never arrived, Pesapal refunds that have been paid, abandoned orders.
// Runs before the hold expiry below, so a guest who has just paid never loses their room to it.
Artisan::command('payments:reconcile', function (OnlinePaymentService $online) {
    $r = $online->reconcile();
    $this->info("Checked {$r['checked']}, settled {$r['settled']}, abandoned {$r['abandoned']}, refunds completed {$r['refunds']}.");
})->purpose('Settle online payments with the payment gateway');
Schedule::command('payments:reconcile')->everyMinute()->withoutOverlapping();

// One-off setup when going live: tells Pesapal where to send payment notifications.
Artisan::command('payments:pesapal-register-ipn {url?}', function (PesapalGateway $pesapal, ?string $url = null) {
    $url ??= rtrim(config('app.url'), '/').'/api/payments/pesapal/ipn';
    if (! str_starts_with($url, 'https://') && app()->isProduction()) {
        $this->error('Pesapal needs a public https address for the IPN.');

        return 1;
    }
    $this->info('Registered '.$url);
    $this->line('Put this in .env: PESAPAL_IPN_ID='.$pesapal->registerIpn($url));

    return 0;
})->purpose('Register the Pesapal IPN address and print its id');

// Unpaid website holds give their room back.
Schedule::call(fn () => app(ReservationService::class)->expireHolds())
    ->everyMinute()->name('reservations:expire-holds')->withoutOverlapping();

// Unpaid website facility bookings are cancelled when their hold runs out.
Schedule::call(fn () => app(FacilityBookingService::class)->expireHolds())
    ->everyMinute()->name('facility-bookings:expire-holds')->withoutOverlapping();

// Pull OTA calendar feeds so their bookings block our rooms.
Schedule::call(fn () => app(ChannelService::class)->syncAll())
    ->everyFifteenMinutes()->name('channels:sync')->withoutOverlapping();
