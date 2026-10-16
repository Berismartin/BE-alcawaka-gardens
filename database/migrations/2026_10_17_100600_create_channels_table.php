<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One iCal link per OTA listing (a listing is one room), which most OTAs accept.
        Schema::create('channels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 120);
            $table->foreignUuid('room_id')->constrained()->cascadeOnDelete();
            $table->string('import_url', 500)->nullable();
            $table->string('export_token', 64)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_sync_status', 20)->nullable(); // ok | failed
            $table->string('last_sync_message', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channels');
    }
};
