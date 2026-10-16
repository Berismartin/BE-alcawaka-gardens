<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('full_name', 120)->index();
            $table->string('phone', 20)->nullable()->index(); // E.164, e.g. +256772123456
            $table->string('email', 160)->nullable()->index();
            $table->string('nationality', 60)->nullable();
            $table->string('id_number', 60)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guests');
    }
};
