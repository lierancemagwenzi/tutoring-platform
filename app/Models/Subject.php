<?php

namespace App\Models;

use App\Enums\SubjectStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Subject extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'status',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'status' => SubjectStatus::class,
        ];
    }

    /**
     * Keep the legacy is_active boolean in sync with status, so the
     * existing public subject listing (which still filters on is_active)
     * keeps working unchanged.
     */
    protected static function booted(): void
    {
        static::creating(function (Subject $subject) {
            if (! $subject->slug) {
                $subject->slug = static::uniqueSlugFor($subject->name);
            }

            if (! $subject->status) {
                $subject->status = $subject->is_active === false ? SubjectStatus::Inactive : SubjectStatus::Active;
            }
        });

        static::saving(function (Subject $subject) {
            if ($subject->isDirty('status')) {
                $subject->is_active = $subject->status === SubjectStatus::Active;
            }
        });
    }

    private static function uniqueSlugFor(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    /**
     * Only subjects available for new tutor offerings and marketplace configuration.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', SubjectStatus::Active);
    }

    /**
     * The tutor assignments referencing this subject.
     */
    public function tutorSubjects(): HasMany
    {
        return $this->hasMany(TutorSubject::class);
    }

    /**
     * The tutoring services offered under this subject.
     */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    /**
     * The self-paced courses authored under this subject.
     */
    public function selfPacedCourses(): HasMany
    {
        return $this->hasMany(SelfPacedCourse::class);
    }
}
