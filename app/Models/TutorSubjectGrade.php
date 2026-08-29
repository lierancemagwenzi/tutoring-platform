<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TutorSubjectGrade extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tutor_subject_id',
        'grade_id',
    ];

    /**
     * The tutor subject assignment this grade entry belongs to.
     */
    public function tutorSubject(): BelongsTo
    {
        return $this->belongsTo(TutorSubject::class);
    }

    /**
     * The grade this entry refers to.
     */
    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }
}
