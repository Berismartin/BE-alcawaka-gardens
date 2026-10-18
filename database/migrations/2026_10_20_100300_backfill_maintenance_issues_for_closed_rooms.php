<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /** Rooms already closed before the maintenance log existed get their open issue, so "Mark fixed" works for them too. */
    public function up(): void
    {
        $rooms = DB::table('rooms')->whereIn('status', ['under_maintenance', 'out_of_order'])->get(['id', 'status_note', 'status_changed_by', 'status_changed_at']);
        foreach ($rooms as $room) {
            DB::table('maintenance_issues')->insert([
                'id' => (string) Str::uuid7(),
                'room_id' => $room->id,
                'description' => $room->status_note ?: 'Closed for repairs',
                'closes_room' => true,
                'reported_by' => $room->status_changed_by,
                'created_at' => $room->status_changed_at ?? now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // The issues stay: they are real records once staff work with them.
    }
};
