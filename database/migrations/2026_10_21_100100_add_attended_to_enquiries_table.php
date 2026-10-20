<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enquiries', function (Blueprint $table) {
            // Who first handled the enquiry and when; empty while it is still "new" (not attended to).
            $table->foreignId('attended_by')->nullable()->after('notes')->constrained('users')->nullOnDelete();
            $table->timestamp('attended_at')->nullable()->after('attended_by');
        });
    }

    public function down(): void
    {
        Schema::table('enquiries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('attended_by');
            $table->dropColumn('attended_at');
        });
    }
};
