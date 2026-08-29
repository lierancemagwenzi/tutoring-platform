<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Widen the enum first so both the old ('booked') and new ('confirmed',
        // 'awaiting_payment') values are valid while the data migrates, then
        // narrow it to the final set once no row references 'booked' anymore.
        Schema::table('bookings', function (Blueprint $table) {
            $table->enum('status', ['pending', 'accepted', 'awaiting_payment', 'confirmed', 'rejected', 'booked', 'cancelled', 'completed', 'expired'])
                ->default('pending')
                ->change();
        });

        DB::table('bookings')->where('status', 'booked')->update(['status' => 'confirmed']);

        Schema::table('bookings', function (Blueprint $table) {
            $table->enum('status', ['pending', 'accepted', 'awaiting_payment', 'confirmed', 'rejected', 'cancelled', 'completed', 'expired'])
                ->default('pending')
                ->change();
            $table->dropColumn('payment_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->enum('status', ['pending', 'accepted', 'awaiting_payment', 'confirmed', 'rejected', 'booked', 'cancelled', 'completed', 'expired'])
                ->default('pending')
                ->change();
            $table->enum('payment_status', ['pending', 'paid'])->default('pending')->after('status');
        });

        DB::table('bookings')->where('status', 'confirmed')->update(['status' => 'booked']);

        Schema::table('bookings', function (Blueprint $table) {
            $table->enum('status', ['pending', 'accepted', 'rejected', 'booked', 'cancelled', 'completed', 'expired'])
                ->default('pending')
                ->change();
        });
    }
};
