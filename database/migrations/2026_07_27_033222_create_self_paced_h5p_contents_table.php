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
        // The H5P server itself has no concept of ownership or "which
        // product this content is for" — listContent() returns everything
        // on the server, globally. This table is the local tag that scopes
        // a subset of that content to "belongs to this tutor's self-paced
        // courses", so the Assessment provider dropdown never shows
        // Tutor-Led Learning's H5P content (or another tutor's).
        Schema::create('self_paced_h5p_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tutor_profile_id')->constrained()->cascadeOnDelete();
            $table->string('h5p_content_id');
            $table->string('title')->nullable();
            $table->timestamps();

            $table->unique(['tutor_profile_id', 'h5p_content_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('self_paced_h5p_contents');
    }
};
