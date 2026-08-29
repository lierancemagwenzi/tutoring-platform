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
        // Which libraries a piece of content actually uses (its resolved
        // dependency tree, not just its main library) — see
        // H5PFrameworkInterface::saveLibraryUsage()/getLibraryUsage()/
        // loadContentDependencies().
        Schema::create('h5p_content_libraries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_id')->constrained('h5p_content')->cascadeOnDelete();
            $table->foreignId('library_id')->constrained('h5p_libraries');
            $table->string('dependency_type'); // editor | preloaded | dynamic
            $table->boolean('drop_css')->default(false);
            $table->unsignedInteger('weight')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('h5p_content_libraries');
    }
};
