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
        // Generic key/value store for H5PFrameworkInterface::getOption()/setOption()
        // — site UUID, content-type-cache-updated-at, hub metadata cache, etc.
        // `value` is longText since the content type cache itself is a large JSON blob.
        Schema::create('h5p_options', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->longText('value')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('h5p_options');
    }
};
