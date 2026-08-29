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
        Schema::create('self_paced_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('self_paced_course_id')->constrained()->cascadeOnDelete();

            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('position')->default(0);

            $table->boolean('activity_completion_required')->default(true);
            $table->boolean('assessment_completion_required')->default(false);

            $table->timestamps();

            $table->index(['self_paced_course_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('self_paced_modules');
    }
};
