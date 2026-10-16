<?php

namespace Database\Seeders;

use App\Domains\Facilities\Facility\Enums\FacilityAccess;
use App\Domains\Users\User\Enums\UserRole;
use App\Models\Facilities\Facility;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Local development only. Never run in production with these credentials.
        $manager = User::firstOrNew(['email' => 'manager@alcawaka.test']);
        $manager->forceFill(['name' => 'Demo Manager', 'password' => 'password', 'role' => UserRole::Manager])->save();

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
    }
}
