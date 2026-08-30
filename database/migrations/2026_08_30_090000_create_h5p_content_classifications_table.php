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
        // Doubles as the ownership record for a piece of H5P content (which
        // has no owner or taxonomy of its own — see h5p_content's migration
        // comment) and its Grade/Subject/Curriculum classification, created
        // atomically the moment content is first saved. All three are
        // required, matching Course's own grade/subject/curriculum columns.
        Schema::create('h5p_content_classifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('h5p_content_id')->constrained('h5p_content')->cascadeOnDelete()->unique();
            $table->foreignId('tutor_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grade_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('curriculum_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->index(['tutor_profile_id', 'subject_id', 'grade_id', 'curriculum_id'], 'h5p_content_classifications_scope_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('h5p_content_classifications');
    }
};
