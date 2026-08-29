<?php

namespace App\Http\Requests\Tutor;

use App\Enums\BookingStatus;
use App\Enums\LessonStatus;
use App\Models\Booking;
use App\Models\Lesson;
use App\Services\Booking\BookingProgressService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ScheduleSessionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $booking = $this->route('booking');

        return $booking instanceof Booking && $booking->tutor_profile_id === $this->user()->tutorProfile?->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lesson_id' => ['nullable', 'integer', 'exists:lessons,id'],
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'tutor_notes' => ['nullable', 'string'],
        ];
    }

    /**
     * Configure the validator instance.
     *
     * Availability/overlap/capacity checks are deliberately not duplicated
     * here — they depend on whether an exact-matching session already
     * exists (the group-class attach path bypasses them entirely), logic
     * that only SessionSchedulingService itself can cheaply evaluate.
     * Those are left to the service's RuntimeException, caught by the
     * controller.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var Booking $booking */
            $booking = $this->route('booking');

            if ($booking->status !== BookingStatus::Confirmed) {
                $validator->errors()->add('booking', 'This booking is not active.');

                return;
            }

            if (app(BookingProgressService::class)->remainingSessions($booking) <= 0) {
                $validator->errors()->add('booking', 'This booking has no remaining sessions to schedule.');

                return;
            }

            if ($validator->errors()->has('lesson_id') || ! $this->filled('lesson_id')) {
                return;
            }

            $lesson = Lesson::find($this->input('lesson_id'));

            if (! $lesson) {
                return;
            }

            if ($lesson->status !== LessonStatus::Published) {
                $validator->errors()->add('lesson_id', 'Only published lessons can be assigned to a session.');

                return;
            }

            if (! $lesson->chapter->course->matchesService($booking->service)) {
                $validator->errors()->add(
                    'lesson_id',
                    "This lesson's subject, grade, and curriculum don't match this booking's service.",
                );
            }
        });
    }
}
