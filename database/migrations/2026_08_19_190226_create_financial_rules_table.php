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
        Schema::create('financial_rules', function (Blueprint $table) {
            $table->id();
            $table->string('scope');
            $table->foreignId('tutor_profile_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('service_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('self_paced_course_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->decimal('percentage', 5, 2);
            $table->decimal('fixed_fee', 10, 2)->default(0);
            $table->decimal('provider_fee_percentage', 5, 2)->nullable();
            $table->decimal('provider_fee_fixed', 10, 2)->nullable();
            $table->string('currency')->default('ZAR');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('scope');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_rules');
    }
};
