<?php

namespace Database\Factories\Guests;

use App\Models\Guests\Guest;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Guest> */
class GuestFactory extends Factory
{
    protected $model = Guest::class;

    public function definition(): array
    {
        return [
            'full_name' => fake()->name(),
            'phone' => '+2567'.fake()->unique()->numerify('########'),
            'email' => fake()->unique()->safeEmail(),
        ];
    }
}
