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
        Schema::create('booking_teaching_session', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teaching_session_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['booking_id', 'teaching_session_id']);
        });

        // Backfill: every existing booking->teaching_session_id becomes one
        // pivot row, preserving today's data under the new many-to-many.
        DB::table('bookings')
            ->whereNotNull('teaching_session_id')
            ->select('id', 'teaching_session_id', 'created_at', 'updated_at')
            ->orderBy('id')
            ->each(function ($booking) {
                DB::table('booking_teaching_session')->insert([
                    'booking_id' => $booking->id,
                    'teaching_session_id' => $booking->teaching_session_id,
                    'created_at' => $booking->created_at,
                    'updated_at' => $booking->updated_at,
                ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_teaching_session');
    }
};
