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
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['teaching_session_id']);
            $table->dropColumn('teaching_session_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * Best-effort only: restores one teaching_session_id per booking from
     * the pivot table (the first attached session), which is lossy if a
     * booking has since gained more than one session.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('teaching_session_id')->nullable()->after('availability_slot_id')->constrained()->nullOnDelete();
        });

        DB::table('booking_teaching_session')
            ->select('booking_id', DB::raw('MIN(teaching_session_id) as teaching_session_id'))
            ->groupBy('booking_id')
            ->orderBy('booking_id')
            ->each(function ($pivot) {
                DB::table('bookings')->where('id', $pivot->booking_id)->update(['teaching_session_id' => $pivot->teaching_session_id]);
            });
    }
};
