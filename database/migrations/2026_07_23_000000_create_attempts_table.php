<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Attempts belong to the Session Lesson Block (the delivery instance),
     * never directly to the Lesson Block — the same reusable H5P/SurveyJS
     * block can be attempted, and scored, independently across many
     * sessions. `provider` plus the two JSON payload columns are what keep
     * this table provider-agnostic: a future provider (Judge0, etc.) needs
     * no new columns, just a new AttemptResultHandler.
     */
    public function up(): void
    {
        Schema::create('attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_lesson_block_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->string('provider');
            $table->unsignedInteger('attempt_number');
            $table->string('status')->default('started');
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('time_taken_seconds')->nullable();
            $table->decimal('raw_score', 8, 2)->nullable();
            $table->decimal('max_score', 8, 2)->nullable();
            $table->decimal('percentage', 5, 2)->nullable();
            $table->boolean('passed')->nullable();
            $table->json('raw_provider_response')->nullable();
            $table->json('provider_metadata')->nullable();
            $table->timestamps();

            $table->index(['session_lesson_block_id', 'student_id']);
            $table->unique(['session_lesson_block_id', 'student_id', 'attempt_number'], 'attempts_block_student_attempt_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attempts');
    }
};
