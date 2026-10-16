<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedInteger('number')->unique(); // shown as ARS-<number>
            $table->foreignUuid('guest_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('room_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('room_type_id')->constrained()->restrictOnDelete();
            $table->date('check_in')->index();
            $table->date('check_out')->index();
            $table->unsignedTinyInteger('adults')->default(1);
            $table->unsignedTinyInteger('children')->default(0);
            $table->string('status', 30)->index();
            $table->string('source', 30)->index();
            $table->text('special_requests')->nullable();
            $table->string('external_uid', 255)->nullable()->unique(); // OTA iCal event UID
            $table->foreignUuid('channel_id')->nullable();
            $table->timestamp('hold_expires_at')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('checked_out_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // One row per occupied night. The unique index is what makes double booking impossible.
        Schema::create('reservation_nights', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('room_id')->constrained()->restrictOnDelete();
            $table->date('night');
            $table->unsignedBigInteger('rate_ugx');
            $table->unique(['room_id', 'night']);
            $table->index('night');
        });

        Schema::create('folio_charges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('reservation_id')->constrained()->cascadeOnDelete();
            $table->string('category', 30);
            $table->string('description', 255);
            $table->unsignedBigInteger('amount_ugx');
            $table->date('posted_on');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['category', 'posted_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('folio_charges');
        Schema::dropIfExists('reservation_nights');
        Schema::dropIfExists('reservations');
    }
};
