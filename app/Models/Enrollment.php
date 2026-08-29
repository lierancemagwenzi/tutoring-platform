<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Enrollment extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'student_id',
        'self_paced_course_id',
        'order_id',
        'status',
        'enrolled_at',
        'completed_at',
        'last_accessed_at',
        'course_version',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => EnrollmentStatus::class,
            'enrolled_at' => 'datetime',
            'completed_at' => 'datetime',
            'last_accessed_at' => 'datetime',
            'course_version' => 'integer',
        ];
    }

    /**
     * The student who owns this enrollment.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * The self-paced course this enrollment grants access to.
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(SelfPacedCourse::class, 'self_paced_course_id');
    }

    /**
     * The order that paid for this enrollment.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * This student's per-module completion records for this enrollment.
     */
    public function moduleProgress(): HasMany
    {
        return $this->hasMany(ModuleProgress::class);
    }

    /**
     * This student's per-activity completion records for this enrollment.
     */
    public function activityProgress(): HasMany
    {
        return $this->hasMany(ActivityProgress::class);
    }

    /**
     * The certificate issued for this enrollment, once the course has been completed.
     */
    public function certificate(): HasOne
    {
        return $this->hasOne(CourseCertificate::class);
    }
}
