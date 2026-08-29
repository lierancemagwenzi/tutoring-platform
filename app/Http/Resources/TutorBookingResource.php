<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TutorBookingResource extends JsonResource
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
            'status' => $this->status->value,
            'order_id' => $this->order_id,
            'date' => $this->date->format('Y-m-d'),
            'start_time' => substr($this->start_time, 0, 5),
            'end_time' => substr($this->end_time, 0, 5),
            'price' => $this->price,
            'currency' => $this->currency->value,
            'message' => $this->message,
            'student' => new BookingStudentResource($this->whenLoaded('student')),
            'service' => new ServiceResource($this->whenLoaded('service')),
            'created_at' => $this->created_at,
        ];
    }
}
