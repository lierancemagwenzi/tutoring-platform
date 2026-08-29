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
        Schema::create('self_paced_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('self_paced_module_id')->constrained()->cascadeOnDelete();

            $table->string('assessment_type');
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('required')->default(true);

            $table->decimal('passing_score', 5, 2)->nullable();
            $table->string('attempts_mode')->default('unlimited');
            $table->unsignedInteger('max_attempts')->nullable();
            $table->unsignedInteger('time_limit_minutes')->nullable();
            $table->timestamp('available_from')->nullable();
            $table->timestamp('available_until')->nullable();
            $table->boolean('randomize_questions')->default(false);
            $table->boolean('show_results')->default(true);
            $table->boolean('show_correct_answers')->default(false);
            $table->decimal('weight', 5, 2)->nullable();

            // The educational configuration above is provider-agnostic; only
            // these two columns describe how the assessment is rendered and
            // scored, so adding a future provider never touches this schema.
            $table->string('provider')->nullable();
            $table->json('provider_config')->nullable();

            $table->timestamps();

            $table->index(['self_paced_module_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('self_paced_assessments');
    }
};
