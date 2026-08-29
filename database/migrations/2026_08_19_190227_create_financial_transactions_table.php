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
        Schema::create('financial_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tutor_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->string('product_type');
            $table->unsignedBigInteger('product_id');
            $table->decimal('gross_amount', 10, 2);
            $table->string('currency');
            $table->foreignId('financial_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('platform_fee_total', 10, 2);
            $table->decimal('tutor_amount', 10, 2);
            $table->timestamp('created_at')->useCurrent();

            $table->index('tutor_profile_id');
            $table->index(['product_type', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_transactions');
    }
};
