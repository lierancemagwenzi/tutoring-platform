<?php

namespace App\Models;

use App\Enums\MediaType;
use App\Enums\SubmissionAttachmentCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubmissionAttachment extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'submission_id',
        'category',
        'media_type',
        'title',
        'file_path',
        'original_name',
        'size',
        'position',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => SubmissionAttachmentCategory::class,
            'media_type' => MediaType::class,
            'size' => 'integer',
            'position' => 'integer',
        ];
    }

    /**
     * The submission this attachment belongs to.
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }
}
