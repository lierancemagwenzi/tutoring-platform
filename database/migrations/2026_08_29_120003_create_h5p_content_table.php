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
        // Core columns match H5PFrameworkInterface::insertContent()/loadContent().
        // The metadata_* columns match H5PMetadata::toDBArray()'s field set exactly
        // (title/authors/license/etc, snake_cased) — that helper assumes a DB schema
        // shaped like this; it's the same set every H5P PHP integration exposes.
        // No per-tutor ownership column: like the old Node h5p-server, this is a
        // shared content library — Laravel's controllers gate access above this
        // layer (see App\Services\LessonBlocks\H5pBlockHandler).
        Schema::create('h5p_content', function (Blueprint $table) {
            $table->id();
            $table->foreignId('library_id')->constrained('h5p_libraries');
            $table->longText('params');
            $table->longText('filtered_parameters')->nullable();
            $table->string('embed_type')->default('div');
            $table->unsignedInteger('disabled_features')->default(0);
            $table->string('slug')->nullable();

            $table->string('metadata_title')->nullable();
            $table->string('metadata_a11y_title')->nullable();
            $table->longText('metadata_authors')->nullable();
            $table->string('metadata_source')->nullable();
            $table->string('metadata_license', 32)->nullable();
            $table->string('metadata_license_version', 10)->nullable();
            $table->text('metadata_license_extras')->nullable();
            $table->text('metadata_author_comments')->nullable();
            $table->smallInteger('metadata_year_from')->nullable();
            $table->smallInteger('metadata_year_to')->nullable();
            $table->longText('metadata_changes')->nullable();
            $table->string('metadata_default_language', 32)->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('h5p_content');
    }
};
