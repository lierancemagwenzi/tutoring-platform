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
        Schema::table('h5p_contents', function (Blueprint $table) {
            $table->boolean('has_bundled_libraries')->default(true)->after('library_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('h5p_contents', function (Blueprint $table) {
            $table->dropColumn('has_bundled_libraries');
        });
    }
};
