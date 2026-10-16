<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facility_bookings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('facility_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('guest_name', 120);
            $table->string('guest_phone', 20)->nullable();
            $table->dateTime('starts_at')->index();
            $table->unsignedInteger('party_size')->default(1);
            $table->string('charge_mode', 20); // included | folio | paid | pay_later
            $table->unsignedBigInteger('amount_ugx')->default(0);
            $table->string('status', 30)->index();
            $table->string('notes', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('reservation_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUuid('facility_booking_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUuid('refund_of_id')->nullable(); // a refund points at the payment it returns
            $table->string('kind', 10)->default('payment'); // payment | refund
            $table->string('method', 30)->index();
            $table->unsignedBigInteger('amount_ugx');
            $table->string('status', 20)->index(); // pending | succeeded | failed
            $table->string('reference', 120)->nullable(); // receipt or provider transaction number
            $table->string('notes', 255)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('facility_bookings');
    }
};
