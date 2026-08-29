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
        Schema::create('session_meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teaching_session_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('provider')->default('google_meet');
            $table->string('calendar_event_id')->nullable();
            $table->string('meeting_id')->nullable();
            $table->string('meeting_url')->nullable();
            $table->string('organizer_email')->nullable();
            $table->string('status')->default('scheduled');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('session_meetings');
    }
};
