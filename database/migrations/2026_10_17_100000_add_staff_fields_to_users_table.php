<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('role');
            $table->timestamp('last_active_at')->nullable()->after('is_active');
            $table->string('invitation_token', 64)->nullable()->after('last_active_at'); // sha256 of the emailed token
            $table->timestamp('invited_at')->nullable()->after('invitation_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'last_active_at', 'invitation_token', 'invited_at']);
        });
    }
};
