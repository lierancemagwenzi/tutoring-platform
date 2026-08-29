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
        Schema::create('self_paced_survey_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('self_paced_survey_content_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->string('type');
            // {title, choices?, correctAnswer?} — the SurveyJS-compatible
            // question shape, mirroring how Tutor-Led Learning's
            // QuizQuestion stores its definition.
            $table->json('definition');
            $table->unsignedInteger('points')->default(1);
            $table->timestamps();

            $table->index(['self_paced_survey_content_id', 'position'], 'sp_survey_questions_content_position_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('self_paced_survey_questions');
    }
};
