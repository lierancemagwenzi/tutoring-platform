<?php

namespace App\Http\Controllers\Api\Student;

use App\Enums\ServiceVisibility;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\IndexBookingAvailabilityRequest;
use App\Models\Service;
use App\Models\TutorProfile;
use App\Services\Booking\BookingAvailabilityService;
use Illuminate\Http\JsonResponse;

class BookingAvailabilityController extends Controller
{
    public function __construct(private readonly BookingAvailabilityService $availabilityService) {}

    /**
     * Return the bookable dates and start times for a tutor's service within a given month.
     */
    public function index(IndexBookingAvailabilityRequest $request, TutorProfile $tutor, Service $service): JsonResponse
    {
        abort_unless(
            $service->tutor_profile_id === $tutor->id && $service->visibility === ServiceVisibility::Published,
            404,
        );

        $month = $request->validated('month') ?? now()->format('Y-m');

        return response()->json([
            'availability' => $this->availabilityService->availableTimesForMonth($tutor, $service, $month),
        ]);
    }
}
