<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // MySQL's TIMESTAMP type only accepts '1970-01-01 00:00:01' and
        // later — FinancialRuleResolverService's epoch-zero sentinel for
        // "the baseline that has always applied" falls outside that range.
        // DATETIME has no such lower bound (or the 2038 upper bound), so
        // it's the correct type for an arbitrary business date like this,
        // not just a workaround. Raw SQL since doctrine/dbal (required for
        // Blueprint::change()) isn't installed in this app.
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            // SQLite has no native TIMESTAMP/DATETIME distinction — both are
            // stored as text/numeric, so there's no column to alter.
            return;
        }

        DB::statement('ALTER TABLE financial_rules MODIFY effective_from DATETIME NULL');

        // Backfill any row left over from before this column existed (e.g.
        // a global rule created before this feature shipped) — treat it as
        // having been effective since it was created, rather than leaving
        // it permanently unresolvable.
        DB::statement('UPDATE financial_rules SET effective_from = created_at WHERE effective_from IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE financial_rules MODIFY effective_from TIMESTAMP NULL');
    }
};
