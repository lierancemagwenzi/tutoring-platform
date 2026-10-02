<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * On MySQL/MariaDB servers running with explicit_defaults_for_timestamp=OFF,
 * a table's first NOT NULL `timestamp` column without an explicit default is
 * silently given DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP — so
 * any update to the row overwrote it with the database clock. That reset an
 * attempt's started_at when it was completed ("Time taken: 118 min" for a
 * two-minute quiz), a certificate's issued_at, an enrollment's enrolled_at,
 * and an OTP's expires_at (instantly expiring a code after a wrong guess).
 *
 * Re-declaring each column with an explicit default removes the implicit
 * ON UPDATE on those servers and changes nothing on any other database.
 */
return new class extends Migration
{
    private const COLUMNS = [
        'attempts' => 'started_at',
        'quiz_attempts' => 'started_at',
        'self_paced_assessment_attempts' => 'started_at',
        'course_certificates' => 'issued_at',
        'enrollments' => 'enrolled_at',
        'email_otps' => 'expires_at',
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $table => $column) {
            Schema::table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->timestamp($column)->useCurrent()->change();
            });
        }
    }

    public function down(): void
    {
        foreach (self::COLUMNS as $table => $column) {
            Schema::table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->timestamp($column)->change();
            });
        }
    }
};
