<?php

namespace App\Models;

use App\Enums\MediaType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SelfPacedActivityAttachment extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'self_paced_activity_id',
        'media_type',
        'title',
        'file_path',
        'external_url',
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
            'media_type' => MediaType::class,
            'size' => 'integer',
            'position' => 'integer',
        ];
    }

    /**
     * The activity this attachment belongs to.
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(SelfPacedActivity::class, 'self_paced_activity_id');
    }
}
