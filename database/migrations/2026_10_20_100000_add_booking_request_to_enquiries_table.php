<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enquiries', function (Blueprint $table) {
            // Set when a guest asks from "My booking" to change dates or cancel: staff apply it in one click.
            $table->foreignUuid('reservation_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('request_type', 10)->nullable()->after('reservation_id');
            $table->string('requested_check_in', 10)->nullable()->after('request_type');
            $table->string('requested_check_out', 10)->nullable()->after('requested_check_in');
        });
    }

    public function down(): void
    {
        Schema::table('enquiries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reservation_id');
            $table->dropColumn(['request_type', 'requested_check_in', 'requested_check_out']);
        });
    }
};
