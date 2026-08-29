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
        // Dependency edges between libraries — see
        // H5PFrameworkInterface::saveLibraryDependencies()/loadLibrary()'s
        // preloadedDependencies/dynamicDependencies/editorDependencies.
        Schema::create('h5p_libraries_libraries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('library_id')->constrained('h5p_libraries')->cascadeOnDelete();
            $table->foreignId('required_library_id')->constrained('h5p_libraries')->cascadeOnDelete();
            $table->string('dependency_type'); // editor | preloaded | dynamic
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('h5p_libraries_libraries');
    }
};
