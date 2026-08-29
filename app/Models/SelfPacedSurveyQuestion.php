<?php

namespace App\Models;

use App\Enums\SelfPacedSurveyQuestionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SelfPacedSurveyQuestion extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'self_paced_survey_content_id',
        'position',
        'type',
        'definition',
        'points',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'type' => SelfPacedSurveyQuestionType::class,
            'definition' => 'array',
            'points' => 'integer',
        ];
    }

    /**
     * The survey this question belongs to.
     */
    public function surveyContent(): BelongsTo
    {
        return $this->belongsTo(SelfPacedSurveyContent::class, 'self_paced_survey_content_id');
    }
}
