<?php

namespace App\Models;

use App\Enums\LessonBlockStatus;
use App\Enums\MediaType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityAttachment extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'learning_activity_id',
        'media_type',
        'title',
        'description',
        'file_path',
        'original_name',
        'size',
        'thumbnail_path',
        'position',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'media_type' => MediaType::class,
            'size' => 'integer',
            'position' => 'integer',
            'status' => LessonBlockStatus::class,
        ];
    }

    /**
     * The learning activity this attachment belongs to.
     */
    public function learningActivity(): BelongsTo
    {
        return $this->belongsTo(LearningActivity::class);
    }
}
