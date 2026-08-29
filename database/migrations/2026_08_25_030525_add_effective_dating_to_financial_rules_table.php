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
        // MySQL won't drop a unique index while a foreign key still relies
        // on it, even within the same ALTER TABLE statement — the FK drops
        // and index drops need to be genuinely separate statements (hence
        // separate Schema::table calls) for MySQL to fully commit the FK
        // removal before the index removal is validated.
        Schema::table('financial_rules', function (Blueprint $table) {
            $table->dropForeign(['tutor_profile_id']);
            $table->dropForeign(['service_id']);
            $table->dropForeign(['self_paced_course_id']);
        });

        Schema::table('financial_rules', function (Blueprint $table) {
            $table->dropUnique(['tutor_profile_id']);
            $table->dropUnique(['service_id']);
            $table->dropUnique(['self_paced_course_id']);
        });

        Schema::table('financial_rules', function (Blueprint $table) {
            // Effective-dating means multiple rows can now exist for the
            // same tutor/service/course over time (one per rate change) —
            // the old "at most one row per target" unique constraints no
            // longer hold; FinancialRuleResolverService now picks the
            // latest row whose effective_from has passed instead.
            $table->foreign('tutor_profile_id')->references('id')->on('tutor_profiles')->nullOnDelete();
            $table->foreign('service_id')->references('id')->on('services')->nullOnDelete();
            $table->foreign('self_paced_course_id')->references('id')->on('self_paced_courses')->nullOnDelete();

            $table->timestamp('effective_from')->nullable()->after('is_active');
            $table->index(['scope', 'tutor_profile_id', 'service_id', 'self_paced_course_id', 'effective_from'], 'financial_rules_resolution_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('financial_rules', function (Blueprint $table) {
            $table->dropIndex('financial_rules_resolution_index');
            $table->dropColumn('effective_from');
            $table->dropForeign(['tutor_profile_id']);
            $table->dropForeign(['service_id']);
            $table->dropForeign(['self_paced_course_id']);
        });

        Schema::table('financial_rules', function (Blueprint $table) {
            $table->unique('tutor_profile_id');
            $table->unique('service_id');
            $table->unique('self_paced_course_id');
        });

        Schema::table('financial_rules', function (Blueprint $table) {
            $table->foreign('tutor_profile_id')->references('id')->on('tutor_profiles')->nullOnDelete();
            $table->foreign('service_id')->references('id')->on('services')->nullOnDelete();
            $table->foreign('self_paced_course_id')->references('id')->on('self_paced_courses')->nullOnDelete();
        });
    }
};
