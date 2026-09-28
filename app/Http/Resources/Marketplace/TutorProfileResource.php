<?php

namespace App\Http\Resources\Marketplace;

use App\Http\Resources\ServiceResource;
use App\Http\Resources\TutorQualificationResource;
use App\Services\Booking\BookingAvailabilityService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class TutorProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $subjects = $this->publishedServices->pluck('subject')->filter()->unique('id')->values();
        $bookableServiceIds = app(BookingAvailabilityService::class)->bookableServiceIds($this->resource, $this->publishedServices);

        return [
            'id' => $this->id,
            'display_name' => $this->display_name,
            'profile_photo' => $this->profile_photo,
            'profile_photo_url' => $this->profile_photo ? Storage::disk('public')->url($this->profile_photo) : null,
            'bio' => $this->bio,
            'years_experience' => $this->years_experience,
            'languages' => $this->languages ?? [],
            'qualifications' => TutorQualificationResource::collection($this->whenLoaded('qualifications')),
            'subjects' => $subjects->map(fn ($subject) => ['id' => $subject->id, 'name' => $subject->name])->values(),
            'services' => $this->publishedServices->map(fn ($service) => [
                ...(new ServiceResource($service))->resolve($request),
                'has_availability' => in_array($service->id, $bookableServiceIds, true),
            ])->values(),
        ];
    }
}
