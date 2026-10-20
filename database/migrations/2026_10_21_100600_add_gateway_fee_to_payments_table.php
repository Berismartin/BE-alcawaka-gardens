<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Pesapal's fee on this payment and who carried it: 'guest' (charged on top) or 'hotel' (taken from it).
            $table->unsignedInteger('gateway_fee_ugx')->nullable()->after('gateway_checked_at');
            $table->string('gateway_fee_payer', 10)->nullable()->after('gateway_fee_ugx');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['gateway_fee_ugx', 'gateway_fee_payer']);
        });
    }
};
