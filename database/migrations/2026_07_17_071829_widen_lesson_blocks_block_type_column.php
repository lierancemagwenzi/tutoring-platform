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
        Schema::table('lesson_blocks', function (Blueprint $table) {
            $table->string('block_type')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lesson_blocks', function (Blueprint $table) {
            $table->enum('block_type', ['rich_text', 'pdf', 'video'])->change();
        });
    }
};
