<?php

namespace App\Models;

use App\Enums\SelfPacedActivityType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SelfPacedActivity extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'self_paced_module_id',
        'type',
        'title',
        'description',
        'position',
        'required',
        'content',
        'settings',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SelfPacedActivityType::class,
            'position' => 'integer',
            'required' => 'boolean',
            'content' => 'array',
            'settings' => 'array',
        ];
    }

    /**
     * The module this activity belongs to.
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(SelfPacedModule::class, 'self_paced_module_id');
    }

    /**
     * The uploaded/linked files backing this activity, when its type
     * requires one (see SelfPacedActivityType::usesAttachment()).
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(SelfPacedActivityAttachment::class)->orderBy('position');
    }

    /**
     * Whether this activity has the content its type requires — an
     * attachment for file-backed types, inline content otherwise. Used by
     * SelfPacedPublishingService to block publishing incomplete courses.
     */
    public function hasRequiredContent(): bool
    {
        if ($this->type->usesAttachment()) {
            return $this->attachments()->exists();
        }

        return match ($this->type) {
            SelfPacedActivityType::RichText => filled($this->content['html'] ?? null),
            SelfPacedActivityType::Mermaid => filled($this->content['syntax'] ?? null),
            SelfPacedActivityType::Katex => filled($this->content['latex'] ?? null),
            SelfPacedActivityType::ExternalResource => filled($this->content['url'] ?? null),
            SelfPacedActivityType::Assignment, SelfPacedActivityType::Homework, SelfPacedActivityType::Reading => filled($this->content['instructions'] ?? null),
            SelfPacedActivityType::H5p => filled($this->content['h5p_content_id'] ?? null),
            default => true,
        };
    }
}
