<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\SelfPacedCourseStatus;
use App\Enums\SelfPacedCourseVisibility;
use App\Enums\SelfPacedDifficulty;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The root of the Course Authoring Engine (Idea B) — a self-paced learning
 * product, entirely independent of Tutor-Led Learning's Course/Chapter/
 * Lesson hierarchy. Never reference SessionLessonBlock, Booking, or
 * TeachingSession from this domain.
 */
class SelfPacedCourse extends Model
{
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tutor_profile_id',
        'subject_id',
        'grade_id',
        'service_category_id',
        'title',
        'subtitle',
        'description',
        'promo_description',
        'thumbnail_path',
        'promo_video_path',
        'difficulty',
        'language',
        'estimated_duration_minutes',
        'learning_objectives',
        'prerequisites',
        'target_audience',
        'status',
        'visibility',
        'price',
        'currency',
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
            'estimated_duration_minutes' => 'integer',
            'learning_objectives' => 'array',
            'prerequisites' => 'array',
            'target_audience' => 'array',
            'difficulty' => SelfPacedDifficulty::class,
            'status' => SelfPacedCourseStatus::class,
            'visibility' => SelfPacedCourseVisibility::class,
            'price' => 'decimal:2',
            'currency' => Currency::class,
            'course_version' => 'integer',
        ];
    }

    /**
     * The tutor who authored this course.
     */
    public function tutorProfile(): BelongsTo
    {
        return $this->belongsTo(TutorProfile::class);
    }

    /**
     * The subject taxonomy this course belongs to — shared marketplace
     * reference data, not part of Tutor-Led Learning's architecture.
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * The grade level this course is aimed at — shared marketplace
     * reference data, not part of Tutor-Led Learning's architecture.
     */
    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    /**
     * The marketplace category this course is listed under.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }

    /**
     * The modules that make up this course, in display order.
     */
    public function modules(): HasMany
    {
        return $this->hasMany(SelfPacedModule::class)->orderBy('position');
    }

    /**
     * The discount codes redeemable against this course.
     */
    public function discountCodes(): HasMany
    {
        return $this->hasMany(SelfPacedDiscountCode::class);
    }

    /**
     * The enrollments granting students access to this course.
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * The certificates issued to students who completed this course.
     */
    public function certificates(): HasManyThrough
    {
        return $this->hasManyThrough(CourseCertificate::class, Enrollment::class);
    }
}
