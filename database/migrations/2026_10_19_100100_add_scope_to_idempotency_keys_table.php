<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** unique(user_id, key) never matched anonymous requests (NULLs differ), so website retries weren't protected. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('idempotency_keys', function (Blueprint $table) {
            $table->string('scope', 40)->default('guest')->after('user_id');
        });
        foreach (DB::table('idempotency_keys')->whereNotNull('user_id')->distinct()->pluck('user_id') as $userId) {
            DB::table('idempotency_keys')->where('user_id', $userId)->update(['scope' => "user:{$userId}"]);
        }
        Schema::table('idempotency_keys', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'key']);
            $table->unique(['scope', 'key']);
        });
    }

    public function down(): void
    {
        Schema::table('idempotency_keys', function (Blueprint $table) {
            $table->dropUnique(['scope', 'key']);
            $table->unique(['user_id', 'key']);
            $table->dropColumn('scope');
        });
    }
};
