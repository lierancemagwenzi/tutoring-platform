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
        // Tracks which combined-JS/CSS cache file hashes depend on which
        // library, so the cache file can be invalidated when that library is
        // updated — see H5PFrameworkInterface::saveCachedAssets()/deleteCachedAssets().
        Schema::create('h5p_libraries_cachedassets', function (Blueprint $table) {
            $table->id();
            $table->string('hash')->index();
            $table->foreignId('library_id')->constrained('h5p_libraries')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('h5p_libraries_cachedassets');
    }
};
