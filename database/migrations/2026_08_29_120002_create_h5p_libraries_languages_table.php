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
        // Per-library UI translation JSON, keyed by language — see
        // H5peditorStorage::getLanguage()/getAvailableLanguages().
        Schema::create('h5p_libraries_languages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('library_id')->constrained('h5p_libraries')->cascadeOnDelete();
            $table->string('language_code');
            $table->longText('language_json');

            $table->unique(['library_id', 'language_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('h5p_libraries_languages');
    }
};
