<?php

namespace App\Models;

use App\Enums\QualificationLevel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TutorQualification extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tutor_profile_id',
        'title',
        'level',
        'field_of_study',
        'institution',
        'start_year',
        'completion_year',
        'is_currently_studying',
        'description',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => QualificationLevel::class,
            'start_year' => 'integer',
            'completion_year' => 'integer',
            'is_currently_studying' => 'boolean',
        ];
    }

    /**
     * The tutor profile this qualification belongs to.
     */
    public function tutorProfile(): BelongsTo
    {
        return $this->belongsTo(TutorProfile::class);
    }
}
