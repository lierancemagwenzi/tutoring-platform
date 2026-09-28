<?php

namespace App\Http\Resources\Marketplace;

use App\Services\Booking\BookingAvailabilityService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TutorCardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $publishedServices = $this->publishedServices;
        $subjects = $publishedServices->pluck('subject')->filter()->unique('id')->values();
        $grades = $publishedServices->pluck('grade')->filter()->unique('id')->sortBy('level')->values();
        $cheapestService = $publishedServices->sortBy('price')->first();

        return [
            'id' => $this->id,
            'display_name' => $this->display_name,
            'profile_photo' => $this->profile_photo,
            'profile_photo_url' => $this->profile_photo ? Storage::disk('public')->url($this->profile_photo) : null,
            'bio' => $this->bio ? Str::limit($this->bio, 160) : null,
            'years_experience' => $this->years_experience,
            'languages' => $this->languages ?? [],
            'subjects' => $subjects->map(fn ($subject) => ['id' => $subject->id, 'name' => $subject->name])->values(),
            'grades' => $grades->map(fn ($grade) => ['id' => $grade->id, 'name' => $grade->name])->values(),
            'starting_price' => $cheapestService?->price,
            'currency' => $cheapestService?->currency?->value,
            'published_services_count' => $publishedServices->count(),
            // False when none of this tutor's services has an open slot, so
            // the listing can flag them before a learner goes date-hunting.
            'has_availability' => app(BookingAvailabilityService::class)->bookableServiceIds($this->resource, $publishedServices) !== [],
        ];
    }
}
