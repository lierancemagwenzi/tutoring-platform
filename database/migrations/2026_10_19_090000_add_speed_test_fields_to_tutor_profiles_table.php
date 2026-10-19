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
        Schema::table('tutor_profiles', function (Blueprint $table) {
            $table->json('speed_test_result')->nullable()->after('onboarding_complete');
            $table->timestamp('speed_test_passed_at')->nullable()->after('speed_test_result');
        });

        // The speed test became a registration prerequisite after these
        // tutors were already onboarded — don't lock them out retroactively.
        DB::table('tutor_profiles')->where('onboarding_complete', true)->update(['speed_test_passed_at' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tutor_profiles', function (Blueprint $table) {
            $table->dropColumn(['speed_test_result', 'speed_test_passed_at']);
        });
    }
};
