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
        Schema::create('self_paced_activity_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('self_paced_activity_id')->constrained()->cascadeOnDelete();

            $table->string('media_type');
            $table->string('title')->nullable();
            $table->string('file_path')->nullable();
            $table->string('external_url')->nullable();
            $table->string('original_name')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->unsignedInteger('position')->default(0);

            $table->timestamps();

            $table->index(['self_paced_activity_id', 'position'], 'sp_activity_attachments_activity_position_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('self_paced_activity_attachments');
    }
};
