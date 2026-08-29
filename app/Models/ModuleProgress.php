<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A student's completion record for one module of one enrollment — learner
 * data, entirely separate from the authoring SelfPacedModule it tracks.
 * "Locked / Current / Unlocked" are never stored here; they're computed at
 * read time from the sequence of these rows (see ModuleProgressService).
 */
class ModuleProgress extends Model
{
    /**
     * Eloquent's pluralization guess ("module_progresses"/"module_progress")
     * doesn't match this table — "progress" doesn't pluralize the way the
     * convention expects, and the guess wouldn't include the self_paced_
     * prefix either way.
     */
    protected $table = 'self_paced_module_progress';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'enrollment_id',
        'self_paced_module_id',
        'started_at',
        'completed_at',
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
     * The authoring module this progress record tracks.
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(SelfPacedModule::class, 'self_paced_module_id');
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }
}
