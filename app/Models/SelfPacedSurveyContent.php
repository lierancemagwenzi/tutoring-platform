<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A reusable SurveyJS question bank, built once via a proper question
 * builder and picked from across any number of self-paced Assessments —
 * entirely independent of Tutor-Led Learning's Quiz/QuizQuestion. Never
 * store a raw SurveyJS JSON blob inline on an Assessment; this is the
 * single place that definition lives.
 */
class SelfPacedSurveyContent extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tutor_profile_id',
        'grade_id',
        'subject_id',
        'curriculum_id',
        'title',
        'description',
    ];

    /**
     * The tutor who authored this survey.
     */
    public function tutorProfile(): BelongsTo
    {
        return $this->belongsTo(TutorProfile::class);
    }

    /**
     * The grade this survey is organized under.
     */
    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    /**
     * The subject this survey is organized under.
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * The curriculum this survey is organized under.
     */
    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(Curriculum::class);
    }

    /**
     * The questions that make up this survey, in order.
     */
    public function questions(): HasMany
    {
        return $this->hasMany(SelfPacedSurveyQuestion::class)->orderBy('position');
    }

    /**
     * The SurveyJS-compatible model this content resolves to — the same
     * shape an Assessment's provider_config used to store inline.
     *
     * @return array{elements: list<array<string, mixed>>}
     */
    public function toSurveyJson(): array
    {
        return [
            'elements' => $this->questions->map(fn (SelfPacedSurveyQuestion $question) => array_filter([
                'type' => $question->type->value,
                'name' => "question_{$question->id}",
                'title' => $question->definition['title'] ?? '',
                'choices' => $question->definition['choices'] ?? null,
            ], fn ($value) => $value !== null))->all(),
        ];
    }
}
