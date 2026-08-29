<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CancelBookingWithRefundRequest;
use App\Http\Resources\Admin\BookingManagementResource;
use App\Models\Booking;
use App\Services\Admin\AdminBookingCancellationService;
use App\Services\Admin\AdminBookingManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingManagementController extends Controller
{
    public function __construct(
        private readonly AdminBookingManagementService $bookings,
        private readonly AdminBookingCancellationService $cancellation,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $bookings = $this->bookings->list($request->only(['status', 'search']), (int) $request->integer('per_page', 15));

        return response()->json([
            'bookings' => BookingManagementResource::collection($bookings->items()),
            'meta' => [
                'current_page' => $bookings->currentPage(),
                'last_page' => $bookings->lastPage(),
                'per_page' => $bookings->perPage(),
                'total' => $bookings->total(),
            ],
        ]);
    }

    public function cancel(CancelBookingWithRefundRequest $request, Booking $booking): JsonResponse
    {
        $booking = $this->cancellation->cancel($booking, $request->user(), $request->validated('reason'));

        return response()->json(['booking' => new BookingManagementResource($booking)]);
    }
}
