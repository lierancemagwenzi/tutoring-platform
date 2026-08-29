<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class SelfPacedModule extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'self_paced_course_id',
        'title',
        'description',
        'position',
        'activity_completion_required',
        'assessment_completion_required',
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
            'activity_completion_required' => 'boolean',
            'assessment_completion_required' => 'boolean',
        ];
    }

    /**
     * The course this module belongs to.
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(SelfPacedCourse::class, 'self_paced_course_id');
    }

    /**
     * The Learning Activities within this module, in display order.
     */
    public function activities(): HasMany
    {
        return $this->hasMany(SelfPacedActivity::class)->orderBy('position');
    }

    /**
     * The Assessments within this module, in display order.
     */
    public function assessments(): HasMany
    {
        return $this->hasMany(SelfPacedAssessment::class)->orderBy('position');
    }

    /**
     * Activities and Assessments merged into a single display sequence —
     * both share the same integer position namespace within a module (see
     * SelfPacedModuleContentService::reorder()), so a sort by position is
     * all that's needed to interleave them correctly.
     *
     * @return Collection<int, SelfPacedActivity|SelfPacedAssessment>
     */
    public function orderedContent(): Collection
    {
        return $this->activities->concat($this->assessments)->sortBy('position')->values();
    }
}
