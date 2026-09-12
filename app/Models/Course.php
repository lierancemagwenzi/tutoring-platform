<?php

namespace App\Models;

use App\Enums\CourseDifficulty;
use App\Enums\CourseStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tutor_profile_id',
        'curriculum_id',
        'grade_id',
        'subject_id',
        'title',
        'description',
        'thumbnail_path',
        'cover_image_path',
        'estimated_duration_minutes',
        'difficulty',
        'language',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estimated_duration_minutes' => 'integer',
            'difficulty' => CourseDifficulty::class,
            'status' => CourseStatus::class,
        ];
    }

    /**
     * The tutor profile that owns this course.
     */
    public function tutorProfile(): BelongsTo
    {
        return $this->belongsTo(TutorProfile::class);
    }

    /**
     * The curriculum this course is aligned to.
     */
    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(Curriculum::class);
    }

    /**
     * The grade this course is intended for.
     */
    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    /**
     * The subject this course is taught in.
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * The chapters that make up this course.
     */
    public function chapters(): HasMany
    {
        return $this->hasMany(Chapter::class)->orderBy('position');
    }

    /**
     * Whether this course's subject, grade, and curriculum align with the
     * given service — the same eligibility check a student's paid booking
     * must satisfy to access this course's content (see
     * StudentCourseAccessService), applied here so a tutor can't assign a
     * lesson students would never actually be allowed to open.
     *
     * A service with no grade requirement (grade_id is null — it isn't
     * pinned to one grade) matches a course of any grade; courses
     * themselves always have a grade, so this can't be a mutual wildcard.
     */
    public function matchesService(Service $service): bool
    {
        return $this->subject_id === $service->subject_id
            && ($service->grade_id === null || $this->grade_id === $service->grade_id)
            && $service->curricula()->whereKey($this->curriculum_id)->exists();
    }

    /**
     * Scope to courses matching a service's subject/grade/curriculum — the
     * query-builder equivalent of matchesService(), used to filter a list of
     * courses down to the ones assignable to a given service (e.g. when a
     * tutor is picking a lesson for a booking's session).
     */
    public function scopeMatchingService(Builder $query, Service $service): Builder
    {
        return $query
            ->where('subject_id', $service->subject_id)
            ->when($service->grade_id !== null, fn ($q) => $q->where('grade_id', $service->grade_id))
            ->whereIn('curriculum_id', $service->curricula()->pluck('curricula.id'));
    }
}
