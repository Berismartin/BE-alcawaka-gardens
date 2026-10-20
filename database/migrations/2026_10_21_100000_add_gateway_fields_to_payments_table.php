<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('gateway', 20)->nullable()->after('method'); // pesapal; null for desk payments
            // Pesapal's order id. Unique: one order can only ever settle one payment row.
            $table->string('gateway_tracking_id', 64)->nullable()->unique()->after('gateway');
            $table->string('gateway_redirect_url', 500)->nullable()->after('gateway_tracking_id');
            $table->string('gateway_confirmation_code', 100)->nullable()->after('gateway_redirect_url');
            $table->string('gateway_payment_method', 50)->nullable()->after('gateway_confirmation_code'); // MTN, Airtel, Visa…
            $table->string('gateway_account', 60)->nullable()->after('gateway_payment_method'); // masked phone or card
            $table->timestamp('gateway_checked_at')->nullable()->after('gateway_account');
            $table->index(['status', 'gateway']); // the reconcile job's lookup
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['status', 'gateway']);
            $table->dropUnique(['gateway_tracking_id']);
            $table->dropColumn(['gateway', 'gateway_tracking_id', 'gateway_redirect_url', 'gateway_confirmation_code', 'gateway_payment_method', 'gateway_account', 'gateway_checked_at']);
        });
    }
};
