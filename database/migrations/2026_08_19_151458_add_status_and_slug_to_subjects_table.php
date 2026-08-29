<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('name');
            $table->string('status')->default('active')->after('description');
        });

        // Backfill existing rows: slug from name, status from the current
        // is_active boolean — is_active itself is kept afterwards (synced by
        // the model going forward) so the existing public subject listing
        // keeps working unchanged.
        foreach (DB::table('subjects')->select('id', 'name', 'is_active')->get() as $subject) {
            DB::table('subjects')->where('id', $subject->id)->update([
                'slug' => Str::slug($subject->name).'-'.$subject->id,
                'status' => $subject->is_active ? 'active' : 'inactive',
            ]);
        }

        Schema::table('subjects', function (Blueprint $table) {
            $table->string('slug')->nullable(false)->change();
        });

        Schema::table('subjects', function (Blueprint $table) {
            $table->unique('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'status']);
        });
    }
};
