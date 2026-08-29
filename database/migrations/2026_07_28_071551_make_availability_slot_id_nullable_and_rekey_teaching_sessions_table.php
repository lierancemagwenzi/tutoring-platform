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
        // tutor_profile_id has no single-column index of its own — MySQL
        // was using the composite unique (tutor_profile_id is its leftmost
        // column) to satisfy that foreign key, so it must be dropped too
        // before the unique index can be dropped.
        Schema::table('teaching_sessions', function (Blueprint $table) {
            $table->dropForeign(['tutor_profile_id']);
            $table->dropForeign(['availability_slot_id']);
        });

        Schema::table('teaching_sessions', function (Blueprint $table) {
            $table->dropUnique('teaching_sessions_identity_unique');
        });

        Schema::table('teaching_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('availability_slot_id')->nullable()->change();
        });

        Schema::table('teaching_sessions', function (Blueprint $table) {
            $table->foreign('tutor_profile_id')->references('id')->on('tutor_profiles')->cascadeOnDelete();
            $table->foreign('availability_slot_id')->references('id')->on('availability_slots')->cascadeOnDelete();
            $table->unique(['tutor_profile_id', 'service_id', 'date', 'start_time'], 'teaching_sessions_identity_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teaching_sessions', function (Blueprint $table) {
            $table->dropForeign(['tutor_profile_id']);
            $table->dropForeign(['availability_slot_id']);
        });

        Schema::table('teaching_sessions', function (Blueprint $table) {
            $table->dropUnique('teaching_sessions_identity_unique');
        });

        Schema::table('teaching_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('availability_slot_id')->nullable(false)->change();
        });

        Schema::table('teaching_sessions', function (Blueprint $table) {
            $table->foreign('tutor_profile_id')->references('id')->on('tutor_profiles')->cascadeOnDelete();
            $table->foreign('availability_slot_id')->references('id')->on('availability_slots')->cascadeOnDelete();
            $table->unique(
                ['tutor_profile_id', 'service_id', 'availability_slot_id', 'date', 'start_time'],
                'teaching_sessions_identity_unique',
            );
        });
    }
};
