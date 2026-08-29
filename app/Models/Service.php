<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\ServiceVisibility;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
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
        'session_format_id',
        'title',
        'description',
        'price',
        'currency',
        'session_duration_minutes',
        'sessions_included',
        'validity_period_days',
        'max_students_per_session',
        'visibility',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'currency' => Currency::class,
            'session_duration_minutes' => 'integer',
            'sessions_included' => 'integer',
            'validity_period_days' => 'integer',
            'max_students_per_session' => 'integer',
            'visibility' => ServiceVisibility::class,
        ];
    }

    /**
     * The tutor profile that owns this service.
     */
    public function tutorProfile(): BelongsTo
    {
        return $this->belongsTo(TutorProfile::class);
    }

    /**
     * The subject this service is taught in.
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * The grade this service is intended for.
     */
    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    /**
     * The category this service belongs to.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }

    /**
     * The format sessions are delivered in.
     */
    public function sessionFormat(): BelongsTo
    {
        return $this->belongsTo(SessionFormat::class);
    }

    /**
     * The learning resources included with this service.
     */
    public function learningResources(): BelongsToMany
    {
        return $this->belongsToMany(LearningResource::class, 'service_learning_resources');
    }

    /**
     * The assessment types included with this service.
     */
    public function assessmentTypes(): BelongsToMany
    {
        return $this->belongsToMany(AssessmentType::class, 'service_assessment_types');
    }

    /**
     * The curricula this service is aligned to.
     */
    public function curricula(): BelongsToMany
    {
        return $this->belongsToMany(Curriculum::class, 'service_curricula');
    }

    /**
     * The bookings students have made for this service.
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
