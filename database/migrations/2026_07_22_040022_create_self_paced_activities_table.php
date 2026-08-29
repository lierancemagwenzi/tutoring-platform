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
        Schema::create('self_paced_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('self_paced_module_id')->constrained()->cascadeOnDelete();

            $table->string('type');
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('required')->default(true);

            // Generic per-type payload (rich text html, mermaid syntax, katex
            // latex, external resource url, assignment/homework/reading
            // instructions) — mirrors LessonBlock's content/settings pattern
            // so no library-specific columns leak into this table.
            $table->json('content')->nullable();
            $table->json('settings')->nullable();

            $table->timestamps();

            $table->index(['self_paced_module_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('self_paced_activities');
    }
};
