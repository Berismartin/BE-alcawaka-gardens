<?php

namespace Database\Seeders;

use App\Domains\Facilities\Facility\Enums\FacilityAccess;
use App\Domains\Users\User\Enums\UserRole;
use App\Models\Facilities\Facility;
use App\Models\Settings\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Local development only. Never run in production with these credentials.
        // One login per role, for trying the permission matrix. Each also has POS PIN 2580 (local only).
        $staff = [
            ['manager@alcawaka.test', 'Demo Manager', UserRole::Manager],
            ['frontdesk@alcawaka.test', 'Demo Front Desk', UserRole::FrontDesk],
            ['housekeeping@alcawaka.test', 'Demo Housekeeping', UserRole::Housekeeping],
            ['owner@alcawaka.test', 'Demo Owner', UserRole::Owner],
        ];
        // Production gets no demo logins: create the first real manager with `php artisan staff:create-manager`.
        if (app()->environment('local', 'testing')) {
            foreach ($staff as [$email, $name, $role]) {
                User::firstOrNew(['email' => $email])->forceFill(['name' => $name, 'password' => 'password', 'role' => $role, 'pin_hash' => Hash::make('2580')])->save();
            }
        }

        // The venue's real contact details. firstOrCreate: never overwrite what staff later change in Settings.
        $contacts = ['phone' => '+256 772 408609', 'email' => 'alcawakagardens@gmail.com', 'address' => 'Bakijjulula, Mubende, Uganda'];
        foreach ($contacts as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value]);
        }

        $facilities = [
            ['Swimming pool', FacilityAccess::Paid, 'Poolside seating and towels. Free for in-house guests, day passes for visitors.', 20000],
            ['Gardens', FacilityAccess::Reservation, 'Lawns and quiet seating areas for photos, events and celebrations.', 300000],
            ['Restaurant and bar', FacilityAccess::FreeForGuests, 'Breakfast, lunch and dinner. Charges go onto the room bill.', null],
            ['Event and meeting space', FacilityAccess::Reservation, 'Space for meetings and private events.', 250000],
        ];

        foreach ($facilities as [$name, $access, $description, $price]) {
            Facility::updateOrCreate(['slug' => Str::slug($name)], [
                'name' => $name,
                'access' => $access,
                'description' => $description,
                'price_ugx' => $price, // placeholder prices: replace with the client's real rates
                'is_active' => true,
            ]);
        }

        $this->call(ContentSeeder::class);

        if (app()->environment('local', 'testing')) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
