<?php

namespace App\Http\Controllers\Api\Tutor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tutor\DestroyAvailabilitySlotRequest;
use App\Http\Requests\Tutor\IndexAvailabilityRequest;
use App\Http\Requests\Tutor\StoreAvailabilitySlotRequest;
use App\Http\Requests\Tutor\UpdateAvailabilitySlotRequest;
use App\Http\Resources\AvailabilityDateResource;
use App\Models\AvailabilitySlot;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class AvailabilityController extends Controller
{
    /**
     * Return the logged in tutor's availability dates (with their slots) for the given month.
     */
    public function index(IndexAvailabilityRequest $request): JsonResponse
    {
        $month = Carbon::createFromFormat('Y-m-d', ($request->validated('month') ?? now()->format('Y-m')).'-01')
            ->startOfMonth();

        $dates = $request->user()->tutorProfile
            ->availabilityDates()
            ->whereBetween('date', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()])
            ->with(['slots' => fn ($query) => $query->orderBy('start_time')])
            ->orderBy('date')
            ->get();

        return response()->json([
            'dates' => AvailabilityDateResource::collection($dates),
        ]);
    }

    /**
     * Add a new availability slot for the logged in tutor, creating the date it belongs to if needed.
     */
    public function store(StoreAvailabilitySlotRequest $request): JsonResponse
    {
        $availabilityDate = $request->user()->tutorProfile
            ->availabilityDates()
            ->firstOrCreate(['date' => $request->validated('date')]);

        $availabilityDate->slots()->create($request->safe()->only(['start_time', 'end_time']));

        return response()->json([
            'date' => new AvailabilityDateResource($availabilityDate->load(['slots' => fn ($query) => $query->orderBy('start_time')])),
        ], 201);
    }

    /**
     * Update the start/end time of an existing availability slot.
     */
    public function update(UpdateAvailabilitySlotRequest $request, AvailabilitySlot $slot): JsonResponse
    {
        $slot->update($request->validated());

        $availabilityDate = $slot->availabilityDate->load(['slots' => fn ($query) => $query->orderBy('start_time')]);

        return response()->json([
            'date' => new AvailabilityDateResource($availabilityDate),
        ]);
    }

    /**
     * Remove an availability slot from the logged in tutor's calendar, along with its date if now empty.
     */
    public function destroy(DestroyAvailabilitySlotRequest $request, AvailabilitySlot $slot): JsonResponse
    {
        $availabilityDate = $slot->availabilityDate;

        $slot->delete();

        if ($availabilityDate->slots()->doesntExist()) {
            $availabilityDate->delete();
            $availabilityDate = null;
        } else {
            $availabilityDate->load(['slots' => fn ($query) => $query->orderBy('start_time')]);
        }

        return response()->json([
            'message' => 'Time slot removed.',
            'date' => $availabilityDate ? new AvailabilityDateResource($availabilityDate) : null,
        ]);
    }
}
