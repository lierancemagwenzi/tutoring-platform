<?php

namespace App\Http\Controllers\Api\Tutor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tutor\StoreBookingMessageRequest;
use App\Http\Resources\BookingMessageResource;
use App\Models\Booking;
use App\Services\Booking\BookingChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingChatController extends Controller
{
    public function __construct(private readonly BookingChatService $chat) {}

    public function index(Request $request, Booking $booking): JsonResponse
    {
        abort_unless($booking->tutor_profile_id === $request->user()->tutorProfile?->id, 403);

        return response()->json([
            'messages' => BookingMessageResource::collection($booking->messages()->with('sender')->get()),
        ]);
    }

    public function store(StoreBookingMessageRequest $request, Booking $booking): JsonResponse
    {
        abort_unless($booking->tutor_profile_id === $request->user()->tutorProfile?->id, 403);

        $message = $this->chat->send($booking, $request->user(), $request->validated('body'));

        return response()->json(['message' => new BookingMessageResource($message->load('sender'))], 201);
    }
}
