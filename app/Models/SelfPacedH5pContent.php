<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A local tag marking a piece of H5P content (which lives entirely on the
 * external H5P server — see App\Services\H5p\H5PService) as belonging to
 * this tutor's self-paced course catalog, as distinct from Tutor-Led
 * Learning's H5P-backed Lesson Blocks. The H5P server has no such concept
 * itself; this table is the only place that scoping is recorded.
 */
class SelfPacedH5pContent extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tutor_profile_id',
        'h5p_content_id',
        'title',
    ];

    /**
     * The tutor this tagged content belongs to.
     */
    public function tutorProfile(): BelongsTo
    {
        return $this->belongsTo(TutorProfile::class);
    }
}
