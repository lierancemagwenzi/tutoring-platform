<?php

namespace App\Http\Controllers\Api\Tutor;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tutor\AcceptBookingRequest;
use App\Http\Requests\Tutor\IndexBookingRequestsRequest;
use App\Http\Requests\Tutor\RejectBookingRequest;
use App\Http\Resources\TutorBookingResource;
use App\Models\Booking;
use App\Notifications\UserNotification;
use App\Services\Booking\BookingAcceptanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingRequestController extends Controller
{
    /**
     * The relations eager-loaded on every booking request response.
     *
     * @var list<string>
     */
    private const WITH = ['student', 'service.subject', 'service.category', 'service.sessionFormat'];

    /**
     * Return the logged in tutor's booking requests, optionally filtered by status.
     */
    public function index(IndexBookingRequestsRequest $request): JsonResponse
    {
        $bookings = $request->user()->tutorProfile
            ->bookings()
            ->with(self::WITH)
            ->when($request->validated('status'), fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->get();

        return response()->json([
            'bookings' => TutorBookingResource::collection($bookings),
        ]);
    }

    /**
     * Show a single booking request belonging to the logged in tutor.
     */
    public function show(Request $request, Booking $booking): JsonResponse
    {
        abort_unless($booking->tutor_profile_id === $request->user()->tutorProfile?->id, 403);

        return response()->json([
            'booking' => new TutorBookingResource($booking->load(self::WITH)),
        ]);
    }

    /**
     * Accept a pending booking request — creates its commerce Order and
     * moves it straight to Awaiting Payment.
     */
    public function accept(AcceptBookingRequest $request, Booking $booking, BookingAcceptanceService $acceptance): JsonResponse
    {
        $booking = $acceptance->accept($booking);

        return response()->json([
            'booking' => new TutorBookingResource($booking->load(self::WITH)),
        ]);
    }

    /**
     * Reject a pending booking request.
     */
    public function reject(RejectBookingRequest $request, Booking $booking): JsonResponse
    {
        $booking->update(['status' => BookingStatus::Rejected]);

        $booking->loadMissing('student');
        $booking->student->notify(new UserNotification(
            type: 'booking.rejected',
            title: 'Booking declined',
            body: "{$request->user()->first_name} declined your booking request.",
            url: '/student/bookings',
        ));

        return response()->json([
            'booking' => new TutorBookingResource($booking->load(self::WITH)),
        ]);
    }
}
