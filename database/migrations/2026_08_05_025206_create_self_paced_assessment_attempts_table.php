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
        Schema::create('self_paced_assessment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('self_paced_assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();

            $table->string('provider')->nullable();
            $table->unsignedInteger('attempt_number');
            $table->string('status');
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

            $table->index(['self_paced_assessment_id', 'student_id'], 'assessment_attempts_assessment_student_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('self_paced_assessment_attempts');
    }
};
