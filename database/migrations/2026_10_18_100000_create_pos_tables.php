<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('pin_hash')->nullable()->after('password');
            $table->unsignedTinyInteger('pin_failed_attempts')->default(0)->after('pin_hash');
            $table->timestamp('pin_locked_until')->nullable()->after('pin_failed_attempts');
        });

        // A touch-screen device a manager registered. PIN sign-in only works from one of these.
        Schema::create('pos_terminals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 80);
            $table->string('secret_hash', 64)->unique(); // sha256 of the key kept on the device
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_terminals');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['pin_hash', 'pin_failed_attempts', 'pin_locked_until']);
        });
    }
};
