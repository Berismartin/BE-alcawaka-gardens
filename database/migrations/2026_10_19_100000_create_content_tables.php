<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Website CMS: event types, what's on, gallery, testimonials and FAQs in one table.
        Schema::create('content_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 30);
            $table->string('title', 160);
            $table->string('slug', 190);
            $table->string('summary', 500)->nullable();
            $table->text('body')->nullable();
            $table->string('image_url', 1000)->nullable();
            $table->json('data')->nullable(); // type-specific fields, see ContentType::dataRules()
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();

            $table->unique(['type', 'slug']);
            $table->index(['type', 'is_published', 'sort_order']);
        });

        Schema::create('enquiries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 120);
            $table->string('phone', 30);
            $table->string('email', 160)->nullable();
            $table->string('event_type', 160)->nullable();
            $table->date('event_date')->nullable();
            $table->unsignedInteger('guests')->nullable();
            $table->text('message');
            $table->string('status', 20)->default('new')->index();
            $table->text('notes')->nullable(); // staff only
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enquiries');
        Schema::dropIfExists('content_entries');
    }
};
