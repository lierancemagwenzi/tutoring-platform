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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('tutor_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('availability_slot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teaching_session_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->decimal('price', 10, 2);
            $table->string('currency');
            $table->enum('status', ['pending', 'accepted', 'rejected', 'booked', 'cancelled', 'completed', 'expired'])->default('pending');
            $table->enum('payment_status', ['pending', 'paid'])->default('pending');
            $table->text('message')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'service_id', 'date', 'start_time'], 'bookings_duplicate_lookup');
            $table->index(['tutor_profile_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
