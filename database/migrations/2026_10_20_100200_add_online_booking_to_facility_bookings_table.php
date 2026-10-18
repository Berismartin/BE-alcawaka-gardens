<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Website facility bookings (A08): the guest's email, the key to their booking page and the payment hold.
        Schema::table('facility_bookings', function (Blueprint $table) {
            $table->string('guest_email', 160)->nullable()->after('guest_phone');
            $table->text('guest_key')->nullable()->after('notes');
            $table->timestamp('hold_expires_at')->nullable()->after('guest_key');
        });
    }

    public function down(): void
    {
        Schema::table('facility_bookings', function (Blueprint $table) {
            $table->dropColumn(['guest_email', 'guest_key', 'hold_expires_at']);
        });
    }
};
