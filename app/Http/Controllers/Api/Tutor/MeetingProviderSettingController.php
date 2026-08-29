<?php

namespace App\Http\Controllers\Api\Tutor;

use App\Enums\ConnectedAccountProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tutor\SelectMeetingProviderRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeetingProviderSettingController extends Controller
{
    /**
     * The logged in tutor's meeting provider preference, plus which
     * providers are implemented this phase (others are shown disabled/
     * "Coming Soon" on the frontend).
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'selected' => $request->user()->tutorProfile->meeting_provider?->value,
            'available' => array_map(fn (ConnectedAccountProvider $provider) => $provider->value, SelectMeetingProviderRequest::IMPLEMENTED),
        ]);
    }

    /**
     * Set the tutor's meeting provider preference.
     */
    public function update(SelectMeetingProviderRequest $request): JsonResponse
    {
        $provider = ConnectedAccountProvider::from($request->string('provider')->toString());

        $request->user()->tutorProfile->update(['meeting_provider' => $provider]);

        return response()->json(['selected' => $provider->value]);
    }
}
