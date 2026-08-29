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
        DB::table('session_meetings')->where('provider', 'google_meet')->update(['provider' => 'google']);

        Schema::table('session_meetings', function (Blueprint $table) {
            $table->string('provider')->default('google')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('session_meetings', function (Blueprint $table) {
            $table->string('provider')->default('google_meet')->change();
        });

        DB::table('session_meetings')->where('provider', 'google')->update(['provider' => 'google_meet']);
    }
};
