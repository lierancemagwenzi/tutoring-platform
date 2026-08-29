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
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('booking_fee_amount', 10, 2)->default(0)->after('discount_amount');
            $table->decimal('booking_fee_percentage', 5, 2)->nullable()->after('booking_fee_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['booking_fee_amount', 'booking_fee_percentage']);
        });
    }
};
