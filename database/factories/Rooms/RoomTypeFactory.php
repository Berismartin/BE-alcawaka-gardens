<?php

namespace Database\Factories\Rooms;

use App\Models\Rooms\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<RoomType> */
class RoomTypeFactory extends Factory
{
    protected $model = RoomType::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true).' room';

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'base_rate_ugx' => 180000,
            'max_guests' => 2,
            'bed_type' => 'Queen bed',
            'features' => ['Wi-Fi'],
            'is_active' => true,
        ];
    }
}
