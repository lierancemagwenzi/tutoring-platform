<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A permanently-issued course-completion certificate. student_name,
 * course_title and tutor_name are deliberately frozen at issuance — a later
 * edit to the student's name or the course's title must never alter an
 * already-issued certificate. verification_uuid is reserved for a future
 * public QR-code verification endpoint; nothing reads it yet.
 */
class CourseCertificate extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'enrollment_id',
        'certificate_number',
        'verification_uuid',
        'student_name',
        'course_title',
        'tutor_name',
        'issued_at',
        'pdf_path',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
        ];
    }

    /**
     * The enrollment this certificate was issued for.
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }
}
