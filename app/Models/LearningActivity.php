<?php

namespace App\Models;

use App\Enums\LearningActivityType;
use App\Enums\LessonBlockStatus;
use App\Enums\SubmissionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A reusable educational resource — an activity's title, instructions,
 * submission type, and intrinsic scoring scale. Delivery behaviour (whether
 * it's currently available, whether completion is tracked/required, attempt
 * limits, passing score) lives on SessionLessonBlock instead, so the same
 * activity can be delivered differently across sessions. See
 * SessionLessonBlock for that layer.
 */
class LearningActivity extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'lesson_id',
        'type',
        'title',
        'description',
        'instructions',
        'status',
        'submission_type',
        'max_score',
        'settings',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => LearningActivityType::class,
            'instructions' => 'array',
            'status' => LessonBlockStatus::class,
            'submission_type' => SubmissionType::class,
            'max_score' => 'decimal:2',
            'settings' => 'array',
        ];
    }

    /**
     * The lesson this activity belongs to.
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * The attachments provided as part of this activity.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(ActivityAttachment::class)->orderBy('position');
    }
}
