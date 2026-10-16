<?php

namespace Database\Factories\Facilities;

use App\Domains\Facilities\Facility\Enums\FacilityAccess;
use App\Models\Facilities\Facility;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Facility> */
class FacilityFactory extends Factory
{
    protected $model = Facility::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'opening_hours' => '07:00 – 19:00',
            'capacity' => 40,
            'access' => FacilityAccess::FreeForGuests,
            'price_ugx' => null,
            'is_active' => true,
        ];
    }
}
