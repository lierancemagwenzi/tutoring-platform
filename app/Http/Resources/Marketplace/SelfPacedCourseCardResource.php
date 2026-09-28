<?php

namespace App\Http\Resources\Marketplace;

use App\Services\Marketplace\SelfPacedCoursePricingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SelfPacedCourseCardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'description' => $this->description ? Str::limit(strip_tags($this->description), 160) : null,
            'thumbnail_path' => $this->thumbnail_path,
            'thumbnail_url' => $this->thumbnail_path ? Storage::disk('public')->url($this->thumbnail_path) : null,
            'is_enrolled' => (bool) ($this->is_enrolled ?? false),
            'subject' => $this->whenLoaded('subject', fn () => $this->subject ? ['id' => $this->subject->id, 'name' => $this->subject->name] : null),
            'grade' => $this->whenLoaded('grade', fn () => $this->grade ? ['id' => $this->grade->id, 'name' => $this->grade->name] : null),
            'difficulty' => $this->difficulty?->value,
            'language' => $this->language,
            'estimated_duration_minutes' => $this->estimated_duration_minutes,
            'pricing' => app(SelfPacedCoursePricingService::class)->resolve($this->resource),
            'tutor' => $this->whenLoaded('tutorProfile', fn () => [
                'id' => $this->tutorProfile->id,
                'display_name' => $this->tutorProfile->display_name,
                'profile_photo' => $this->tutorProfile->profile_photo,
                'profile_photo_url' => $this->tutorProfile->profile_photo ? Storage::disk('public')->url($this->tutorProfile->profile_photo) : null,
            ]),
            'modules_count' => $this->whenCounted('modules'),
        ];
    }
}
