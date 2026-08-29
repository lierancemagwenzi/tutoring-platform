<?php

namespace App\Http\Resources;

use App\Services\SelfPaced\SelfPacedPublishingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SelfPacedCourseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $publishing = app(SelfPacedPublishingService::class);

        return [
            'id' => $this->id,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'description' => $this->description,
            'promo_description' => $this->promo_description,
            'thumbnail_path' => $this->thumbnail_path,
            'promo_video_path' => $this->promo_video_path,
            'subject' => new SubjectResource($this->whenLoaded('subject')),
            'grade' => new GradeResource($this->whenLoaded('grade')),
            'category' => new ServiceCategoryResource($this->whenLoaded('category')),
            'difficulty' => $this->difficulty?->value,
            'language' => $this->language,
            'estimated_duration_minutes' => $this->estimated_duration_minutes,
            'learning_objectives' => $this->learning_objectives ?? [],
            'prerequisites' => $this->prerequisites ?? [],
            'target_audience' => $this->target_audience ?? [],
            'status' => $this->status->value,
            'visibility' => $this->visibility->value,
            'price' => $this->price,
            'currency' => $this->currency?->value,
            'modules_count' => $this->whenCounted('modules'),
            'publishing_errors' => $this->when($request->boolean('with_publishing_errors'), fn () => $publishing->errors($this->resource)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
