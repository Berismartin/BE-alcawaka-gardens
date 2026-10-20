<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Enquiries already contacted, booked or closed before "attended" existed were attended to: when is their last update, who is unknown.
    public function up(): void
    {
        DB::table('enquiries')->where('status', '!=', 'new')->whereNull('attended_at')->update(['attended_at' => DB::raw('updated_at')]);
    }

    public function down(): void {}
};
