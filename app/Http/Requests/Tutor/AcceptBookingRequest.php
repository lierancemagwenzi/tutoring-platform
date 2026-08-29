<?php

namespace App\Http\Requests\Tutor;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Services\Booking\BookingAvailabilityService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AcceptBookingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $booking = $this->route('booking');

        if ($booking instanceof Booking) {
            return $booking->tutor_profile_id === $this->user()->tutorProfile?->id;
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var Booking $booking */
            $booking = $this->route('booking');

            if ($booking->status !== BookingStatus::Pending) {
                $validator->errors()->add('status', 'This booking request has already been actioned.');

                return;
            }

            $booking->loadMissing(['service', 'availabilitySlot']);

            $available = (new BookingAvailabilityService)->isTimeAvailable(
                $booking->service,
                $booking->availabilitySlot,
                $booking->date->format('Y-m-d'),
                $booking->start_time,
                $booking->id,
            );

            if (! $available) {
                $validator->errors()->add('status', 'This session has reached capacity and can no longer be accepted.');
            }
        });
    }
}
