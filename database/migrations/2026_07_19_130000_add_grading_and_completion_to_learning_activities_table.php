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
        Schema::table('learning_activities', function (Blueprint $table) {
            $table->boolean('is_graded')->default(true)->after('status');
            $table->string('completion_type')->default('manual')->after('passing_score');
            $table->json('completion_conditions')->nullable()->after('completion_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('learning_activities', function (Blueprint $table) {
            $table->dropColumn(['is_graded', 'completion_type', 'completion_conditions']);
        });
    }
};
