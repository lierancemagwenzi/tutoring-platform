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
        Schema::create('course_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('certificate_number')->unique();
            // The future QR-code / public verification target — reserved
            // now, not consumed by anything yet.
            $table->uuid('verification_uuid')->unique();

            // Snapshot fields, frozen at issuance so a later name/course
            // edit can never alter an already-issued certificate.
            $table->string('student_name');
            $table->string('course_title');
            $table->string('tutor_name');

            $table->timestamp('issued_at');
            $table->string('pdf_path')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_certificates');
    }
};
