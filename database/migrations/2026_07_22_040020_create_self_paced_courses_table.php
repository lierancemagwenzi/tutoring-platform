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
        Schema::create('self_paced_courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tutor_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_category_id')->nullable()->constrained()->nullOnDelete();

            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->text('description')->nullable();
            $table->text('promo_description')->nullable();
            $table->string('thumbnail_path')->nullable();
            $table->string('promo_video_path')->nullable();

            $table->string('difficulty')->nullable();
            $table->string('language')->nullable();
            $table->unsignedInteger('estimated_duration_minutes')->nullable();

            $table->json('learning_objectives')->nullable();
            $table->json('prerequisites')->nullable();
            $table->json('target_audience')->nullable();

            $table->string('status')->default('draft');
            $table->string('visibility')->default('private');

            $table->decimal('price', 10, 2)->nullable();
            $table->string('currency')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tutor_profile_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('self_paced_courses');
    }
};
