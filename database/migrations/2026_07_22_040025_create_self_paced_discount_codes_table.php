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
        Schema::create('self_paced_discount_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('self_paced_course_id')->constrained()->cascadeOnDelete();

            $table->string('code');
            $table->string('discount_type');
            $table->decimal('discount_value', 10, 2);

            $table->unsignedInteger('max_redemptions')->nullable();
            $table->unsignedInteger('times_redeemed')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('active')->default(true);

            $table->timestamps();

            $table->unique(['self_paced_course_id', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('self_paced_discount_codes');
    }
};
