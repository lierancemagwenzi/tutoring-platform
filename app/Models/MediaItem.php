<?php

namespace App\Models;

use App\Enums\LessonBlockStatus;
use App\Enums\MediaType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaItem extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'lesson_block_id',
        'media_type',
        'title',
        'description',
        'file_path',
        'external_url',
        'thumbnail_path',
        'original_name',
        'size',
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
     * The lesson block this media item belongs to.
     */
    public function lessonBlock(): BelongsTo
    {
        return $this->belongsTo(LessonBlock::class);
    }
}
