<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seasonal_discounts', function (Blueprint $table) {
            // Switched off: kept for next season, but never applied to a price.
            $table->boolean('is_active')->default(true)->after('percent');
        });
    }

    public function down(): void
    {
        Schema::table('seasonal_discounts', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
