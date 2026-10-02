<?php

namespace App\Models;

use App\Enums\LessonBlockStatus;
use App\Enums\MediaType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

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

    /**
     * Delete this item's stored file and thumbnail — but only where no other
     * media item still points at the same path. Duplicating a block copies
     * its media rows while sharing the underlying files, so deleting the copy
     * must not pull files out from under the original (or vice versa).
     *
     * @param  list<string>  $columns
     */
    public function deleteFilesIfUnshared(array $columns = ['file_path', 'thumbnail_path']): void
    {
        foreach ($columns as $column) {
            $path = $this->{$column};

            if (! $path) {
                continue;
            }

            $sharedElsewhere = static::query()->whereKeyNot($this->getKey())->where($column, $path)->exists();

            if (! $sharedElsewhere) {
                Storage::disk('public')->delete($path);
            }
        }
    }
}
