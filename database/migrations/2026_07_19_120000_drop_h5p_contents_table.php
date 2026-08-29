<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * H5P content is no longer stored in Laravel's database; it now lives
     * on the dedicated H5P server, referenced from lesson_blocks.content by
     * id only. See app/Services/H5p/H5PService.php.
     */
    public function up(): void
    {
        Schema::dropIfExists('h5p_contents');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('h5p_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tutor_profile_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('package_path');
            $table->string('extracted_path')->nullable();
            $table->string('library_name')->nullable();
            $table->string('thumbnail_path')->nullable();
            $table->boolean('has_bundled_libraries')->default(true);
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->timestamps();

            $table->index('tutor_profile_id');
        });
    }
};
