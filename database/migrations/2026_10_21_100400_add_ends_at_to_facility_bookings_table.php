<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facility_bookings', function (Blueprint $table) {
            // Optional end: an event from Saturday 14:00 to Sunday 02:00, or a meeting room for an afternoon.
            $table->dateTime('ends_at')->nullable()->after('starts_at');
        });
    }

    public function down(): void
    {
        Schema::table('facility_bookings', function (Blueprint $table) {
            $table->dropColumn('ends_at');
        });
    }
};
