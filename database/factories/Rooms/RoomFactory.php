<?php

namespace Database\Factories\Rooms;

use App\Models\Rooms\Room;
use App\Models\Rooms\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Room> */
class RoomFactory extends Factory
{
    protected $model = Room::class;

    public function definition(): array
    {
        return [
            'number' => (string) fake()->unique()->numberBetween(100, 999),
            'floor' => 1,
            'room_type_id' => RoomType::factory(),
            'status' => 'available',
            'status_changed_at' => now(),
        ];
    }
}
