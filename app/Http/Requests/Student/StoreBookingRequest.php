<?php

namespace App\Http\Requests\Student;

use App\Enums\BookingStatus;
use App\Enums\ServiceVisibility;
use App\Enums\UserRole;
use App\Models\AvailabilitySlot;
use App\Models\Booking;
use App\Models\Service;
use App\Models\TutorProfile;
use App\Services\Booking\BookingAvailabilityService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreBookingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->role === UserRole::Student;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'availability_slot_id' => ['required', 'integer', Rule::exists('availability_slots', 'id')],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'message' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date.after_or_equal' => 'Past dates cannot be booked.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var TutorProfile $tutor */
            $tutor = $this->route('tutor');
            /** @var Service $service */
            $service = $this->route('service');

            if ($service->tutor_profile_id !== $tutor->id || $service->visibility !== ServiceVisibility::Published) {
                $validator->errors()->add('service', 'This service is not available for booking.');

                return;
            }

            $slot = AvailabilitySlot::query()->find($this->input('availability_slot_id'));

            if (! $slot
                || $slot->availabilityDate->tutor_profile_id !== $tutor->id
                || $slot->availabilityDate->date->format('Y-m-d') !== $this->input('date')) {
                $validator->errors()->add('availability_slot_id', 'This availability slot is not valid for the selected date.');

                return;
            }

            if (! (new BookingAvailabilityService)->isTimeAvailable($service, $slot, $this->input('date'), $this->input('start_time'))) {
                $validator->errors()->add('start_time', 'This time is no longer available.');

                return;
            }

            $duplicatePending = Booking::query()
                ->where('student_id', $this->user()->id)
                ->where('service_id', $service->id)
                ->where('date', $this->input('date'))
                ->where('start_time', $this->input('start_time'))
                ->where('status', BookingStatus::Pending->value)
                ->exists();

            if ($duplicatePending) {
                $validator->errors()->add('start_time', 'You already have a pending request for this time.');
            }
        });
    }
}
