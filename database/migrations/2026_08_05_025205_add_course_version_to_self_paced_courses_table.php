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
        Schema::table('self_paced_courses', function (Blueprint $table) {
            // Bumped whenever a future authoring workflow republishes
            // structural changes to a course already in progress for
            // students — captured onto Enrollment at enrollment time so
            // learner progress can eventually be reconciled against the
            // version of the course content it was actually earned against.
            $table->unsignedInteger('course_version')->default(1)->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('self_paced_courses', function (Blueprint $table) {
            $table->dropColumn('course_version');
        });
    }
};
