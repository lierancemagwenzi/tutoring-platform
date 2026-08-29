<?php

namespace App\Http\Controllers\Api\Student;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\CancelBookingRequest;
use App\Http\Requests\Student\IndexBookingRequest;
use App\Http\Requests\Student\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\Service;
use App\Models\TutorProfile;
use App\Notifications\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class BookingController extends Controller
{
    /**
     * The relations eager-loaded on every booking response.
     *
     * @var list<string>
     */
    private const WITH = [
        'tutorProfile',
        'service.subject',
        'service.category',
        'service.sessionFormat',
        'service.learningResources',
        'service.assessmentTypes',
        'service.curricula',
        'teachingSessions.sessionMeeting',
        'teachingSessions.sessionLessons.lesson',
    ];

    /**
     * Return the logged in student's bookings, optionally filtered by status.
     */
    public function index(IndexBookingRequest $request): JsonResponse
    {
        $bookings = $request->user()->bookings()
            ->with(self::WITH)
            ->when($request->validated('status'), fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->get();

        return response()->json([
            'bookings' => BookingResource::collection($bookings),
        ]);
    }

    /**
     * Create a new booking request for a tutor's service.
     */
    public function store(StoreBookingRequest $request, TutorProfile $tutor, Service $service): JsonResponse
    {
        $startTime = $request->validated('start_time');

        $booking = Booking::create([
            'student_id' => $request->user()->id,
            'tutor_profile_id' => $tutor->id,
            'service_id' => $service->id,
            'availability_slot_id' => $request->validated('availability_slot_id'),
            'date' => $request->validated('date'),
            'start_time' => $startTime,
            'end_time' => Carbon::parse($startTime)->addMinutes($service->session_duration_minutes)->format('H:i'),
            'price' => $service->price,
            'currency' => $service->currency,
            'status' => BookingStatus::Pending,
            'message' => $request->validated('message'),
        ]);

        $service->loadMissing('subject');
        $tutor->user->notify(new UserNotification(
            type: 'booking.created',
            title: 'New booking request',
            body: "{$request->user()->first_name} requested a {$service->subject->name} session.",
            url: '/tutor/booking-requests',
        ));

        return response()->json([
            'booking' => new BookingResource($booking->load(self::WITH)),
        ], 201);
    }

    /**
     * Show a single booking belonging to the logged in student.
     */
    public function show(Request $request, Booking $booking): JsonResponse
    {
        abort_unless($booking->student_id === $request->user()->id, 403);

        return response()->json([
            'booking' => new BookingResource($booking->load(self::WITH)),
        ]);
    }

    /**
     * Cancel a booking that hasn't been paid for yet.
     */
    public function cancel(CancelBookingRequest $request, Booking $booking): JsonResponse
    {
        $booking->update(['status' => BookingStatus::Cancelled]);

        $booking->loadMissing('tutorProfile.user');
        $booking->tutorProfile->user->notify(new UserNotification(
            type: 'booking.cancelled',
            title: 'Booking cancelled',
            body: "{$request->user()->first_name} cancelled their upcoming booking.",
            url: "/tutor/bookings/{$booking->id}",
        ));

        return response()->json([
            'booking' => new BookingResource($booking->load(self::WITH)),
        ]);
    }
}
