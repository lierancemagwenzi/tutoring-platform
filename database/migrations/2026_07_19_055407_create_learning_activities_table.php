<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('learning_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('instructions')->nullable();
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->string('submission_type');
            $table->string('availability_mode')->default('immediate');
            $table->timestamp('opens_at')->nullable();
            $table->timestamp('closes_at')->nullable();
            $table->string('submission_window_mode')->default('always_open');
            $table->timestamp('submission_closes_at')->nullable();
            $table->boolean('late_submission_allowed')->default(false);
            $table->string('attempts_mode')->default('unlimited');
            $table->unsignedInteger('max_attempts')->nullable();
            $table->decimal('max_score', 8, 2)->nullable();
            $table->decimal('passing_score', 8, 2)->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index('lesson_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('learning_activities');
    }
};
