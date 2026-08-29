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
        // A reusable SurveyJS question bank, built once and picked from
        // across any number of self-paced Assessments — dedicated to
        // self-paced courses and organized by grade/subject/curriculum so a
        // tutor teaching several combinations can keep their library
        // navigable.
        Schema::create('self_paced_survey_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tutor_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grade_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('curriculum_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['tutor_profile_id', 'grade_id', 'subject_id', 'curriculum_id'], 'sp_survey_contents_scope_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('self_paced_survey_contents');
    }
};
