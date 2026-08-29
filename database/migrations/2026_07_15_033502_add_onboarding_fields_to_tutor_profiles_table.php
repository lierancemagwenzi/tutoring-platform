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
        Schema::table('tutor_profiles', function (Blueprint $table) {
            $table->string('display_name')->nullable()->after('user_id');
            $table->unsignedSmallInteger('years_experience')->nullable()->after('profile_photo');
            $table->string('occupation')->nullable()->after('years_experience');
            $table->json('languages')->nullable()->after('occupation');
            $table->text('teaching_style')->nullable()->after('languages');
            $table->text('about_me')->nullable()->after('teaching_style');
            $table->text('why_choose_me')->nullable()->after('about_me');
            $table->string('government_id_path')->nullable()->after('why_choose_me');
            $table->string('government_id_name')->nullable()->after('government_id_path');
            $table->unsignedTinyInteger('onboarding_step')->default(1)->after('government_id_name');
            $table->boolean('onboarding_complete')->default(false)->after('onboarding_step');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tutor_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'display_name',
                'years_experience',
                'occupation',
                'languages',
                'teaching_style',
                'about_me',
                'why_choose_me',
                'government_id_path',
                'government_id_name',
                'onboarding_step',
                'onboarding_complete',
            ]);
        });
    }
};
