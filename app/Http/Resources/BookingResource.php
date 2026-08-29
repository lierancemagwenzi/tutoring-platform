<?php

namespace App\Http\Resources;

use App\Enums\SessionStatus;
use App\Services\Commerce\BookingFeeService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // A preview of what the student will actually pay — the tutor's
        // price plus the Platform & Booking Fee, shown before checkout so
        // AC-01's "itemised breakdown before confirming payment" holds even
        // on the booking detail page, not just the order summary.
        $fee = app(BookingFeeService::class)->calculate((float) $this->price);

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'order_id' => $this->order_id,
            'date' => $this->date->format('Y-m-d'),
            'start_time' => substr($this->start_time, 0, 5),
            'end_time' => substr($this->end_time, 0, 5),
            'price' => $this->price,
            'platform_booking_fee' => $fee['amount'],
            'total_payable' => round((float) $this->price + $fee['amount'], 2),
            'currency' => $this->currency->value,
            'message' => $this->message,
            'tutor' => new BookingTutorResource($this->whenLoaded('tutorProfile')),
            'service' => new ServiceResource($this->whenLoaded('service')),
            // Students only see confirmed Scheduled/Completed occurrences —
            // never a session that was Cancelled before it happened.
            'sessions' => $this->whenLoaded(
                'teachingSessions',
                fn () => TeachingSessionResource::collection(
                    $this->teachingSessions->whereIn('status', [SessionStatus::Scheduled, SessionStatus::Completed])
                        ->sortBy(fn ($session) => $session->date->format('Y-m-d').$session->start_time)
                        ->values()
                ),
            ),
            'created_at' => $this->created_at,
        ];
    }
}
