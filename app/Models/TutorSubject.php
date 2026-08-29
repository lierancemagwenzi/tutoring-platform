<?php

namespace App\Models;

use App\Enums\TutorSubjectStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class TutorSubject extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tutor_profile_id',
        'subject_id',
        'status',
        'is_active',
        'approved_at',
        'rejected_at',
        'approved_by',
        'rejection_reason',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'status' => TutorSubjectStatus::class,
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    /**
     * The tutor profile this subject assignment belongs to.
     */
    public function tutorProfile(): BelongsTo
    {
        return $this->belongsTo(TutorProfile::class);
    }

    /**
     * The subject being taught.
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * The grade entries linking this assignment to individual grades.
     */
    public function tutorSubjectGrades(): HasMany
    {
        return $this->hasMany(TutorSubjectGrade::class);
    }

    /**
     * The individual grades this tutor teaches this subject to.
     */
    public function grades(): HasManyThrough
    {
        return $this->hasManyThrough(
            Grade::class,
            TutorSubjectGrade::class,
            'tutor_subject_id',
            'id',
            'id',
            'grade_id',
        );
    }
}
