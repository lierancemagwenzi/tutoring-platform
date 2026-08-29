<?php

namespace App\Models;

use App\Enums\SelfPacedAssessmentProvider;
use App\Enums\SelfPacedAssessmentType;
use App\Enums\SelfPacedAttemptsMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SelfPacedAssessment extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'self_paced_module_id',
        'assessment_type',
        'title',
        'description',
        'position',
        'required',
        'passing_score',
        'attempts_mode',
        'max_attempts',
        'time_limit_minutes',
        'available_from',
        'available_until',
        'randomize_questions',
        'show_results',
        'show_correct_answers',
        'weight',
        'provider',
        'provider_config',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assessment_type' => SelfPacedAssessmentType::class,
            'position' => 'integer',
            'required' => 'boolean',
            'passing_score' => 'decimal:2',
            'attempts_mode' => SelfPacedAttemptsMode::class,
            'max_attempts' => 'integer',
            'time_limit_minutes' => 'integer',
            'available_from' => 'datetime',
            'available_until' => 'datetime',
            'randomize_questions' => 'boolean',
            'show_results' => 'boolean',
            'show_correct_answers' => 'boolean',
            'weight' => 'decimal:2',
            'provider' => SelfPacedAssessmentProvider::class,
            'provider_config' => 'array',
        ];
    }

    /**
     * The module this assessment belongs to.
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(SelfPacedModule::class, 'self_paced_module_id');
    }

    /**
     * Every student's attempts against this assessment, across all enrollments.
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(SelfPacedAssessmentAttempt::class, 'self_paced_assessment_id');
    }

    /**
     * Whether a rendering provider has been selected and given enough
     * configuration to actually deliver this assessment — the check
     * SelfPacedPublishingService uses to block publishing a course with an
     * unconfigured required assessment.
     */
    public function isConfigured(): bool
    {
        if ($this->provider === null) {
            return false;
        }

        return match ($this->provider) {
            SelfPacedAssessmentProvider::SurveyJs => filled($this->provider_config['survey_content_id'] ?? null),
            SelfPacedAssessmentProvider::H5p => filled($this->provider_config['h5p_content_id'] ?? null),
        };
    }
}
