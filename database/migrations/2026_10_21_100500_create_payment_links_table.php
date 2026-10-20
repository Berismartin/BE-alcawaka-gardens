<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // An invoice emailed to a guest with a "pay now" link to our own /pay/{token} page, which opens a fresh
        // Pesapal checkout for what is still due. The token is the secret; it never charges more than is owed.
        Schema::create('payment_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('token', 64)->unique();
            $table->foreignUuid('reservation_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignUuid('facility_booking_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount_ugx')->nullable(); // null = whatever is due when the guest pays
            $table->string('email', 160);
            $table->string('message', 500)->nullable();
            $table->foreignUuid('last_payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_links');
    }
};
