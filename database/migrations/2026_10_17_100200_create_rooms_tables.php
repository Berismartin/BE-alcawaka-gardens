<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('base_rate_ugx');
            $table->unsignedTinyInteger('max_guests')->default(2);
            $table->string('bed_type', 60)->nullable();
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('rooms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('number', 20)->unique();
            $table->smallInteger('floor')->nullable()->index();
            $table->foreignUuid('room_type_id')->constrained()->restrictOnDelete();
            $table->string('status', 30)->default('available')->index();
            $table->string('status_note', 255)->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('status_changed_at')->nullable();
            $table->foreignId('status_changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('seasonal_discounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 120);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->foreignUuid('room_type_id')->nullable()->constrained()->cascadeOnDelete(); // null = all rooms
            $table->unsignedTinyInteger('percent');
            $table->timestamps();
            $table->index(['starts_on', 'ends_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seasonal_discounts');
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('room_types');
    }
};
