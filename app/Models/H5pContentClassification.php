<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * H5P content (h5p_content, managed entirely by App\Services\H5p\H5PService
 * via the h5p/h5p-core package) has no owner or taxonomy of its own — this is
 * the local record that gives a piece of content both: the tutor who owns it,
 * and the Grade/Subject/Curriculum it's classified under. One row per
 * h5p_content_id, created the moment content is first saved (see
 * App\Http\Controllers\Api\Tutor\H5pContentController::store()).
 */
class H5pContentClassification extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'h5p_content_id',
        'tutor_profile_id',
        'grade_id',
        'subject_id',
        'curriculum_id',
    ];

    /**
     * The tutor who owns this piece of H5P content.
     */
    public function tutorProfile(): BelongsTo
    {
        return $this->belongsTo(TutorProfile::class);
    }

    /**
     * The grade this content is organized under.
     */
    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    /**
     * The subject this content is organized under.
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * The curriculum this content is organized under.
     */
    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(Curriculum::class);
    }
}
