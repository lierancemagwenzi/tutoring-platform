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
        // Column set mirrors exactly what H5PFrameworkInterface::saveLibraryData()/
        // loadLibrary() read and write (see vendor/h5p/h5p-core/h5p.classes.php) —
        // this is the same shape every H5P PHP integration (Drupal, WordPress,
        // Moodle) persists, not something invented for this app.
        Schema::create('h5p_libraries', function (Blueprint $table) {
            $table->id();
            $table->string('machine_name');
            $table->string('title');
            $table->unsignedInteger('major_version');
            $table->unsignedInteger('minor_version');
            $table->unsignedInteger('patch_version');
            $table->boolean('runnable')->default(false);
            $table->boolean('fullscreen')->default(false);
            $table->boolean('has_icon')->default(false);
            $table->boolean('restricted')->default(false);
            $table->string('embed_types')->nullable();
            $table->text('preloaded_js')->nullable();
            $table->text('preloaded_css')->nullable();
            $table->text('drop_library_css')->nullable();
            $table->longText('semantics')->nullable();
            $table->string('tutorial_url')->nullable();
            // { disable, disableExtraTitleField } — see H5PMetadata::boolifyAndEncodeSettings().
            $table->text('metadata_settings')->nullable();
            $table->timestamps();

            $table->unique(['machine_name', 'major_version', 'minor_version', 'patch_version'], 'h5p_libraries_version_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('h5p_libraries');
    }
};
