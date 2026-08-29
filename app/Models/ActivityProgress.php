<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A student's completion record for one SelfPacedActivity (a simple content
 * block — rich text, video, PDF, ...) of one enrollment. Learner data,
 * entirely separate from the authoring SelfPacedActivity it tracks.
 */
class ActivityProgress extends Model
{
    /**
     * Eloquent's pluralization guess doesn't match this table — see
     * ModuleProgress::$table for the same reasoning.
     */
    protected $table = 'self_paced_activity_progress';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'enrollment_id',
        'self_paced_activity_id',
        'started_at',
        'completed_at',
        'time_spent_seconds',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'time_spent_seconds' => 'integer',
        ];
    }

    /**
     * The enrollment this progress record belongs to.
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /**
     * The authoring activity this progress record tracks.
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(SelfPacedActivity::class, 'self_paced_activity_id');
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }
}
