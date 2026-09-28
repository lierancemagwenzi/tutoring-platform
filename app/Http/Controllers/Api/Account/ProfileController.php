<?php

namespace App\Http\Controllers\Api\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\UpdatePasswordRequest;
use App\Http\Requests\Account\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    /**
     * Update the signed-in user's own profile details (any role).
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();

        DB::transaction(function () use ($request, $user) {
            $user->update($request->safe()->only(['first_name', 'last_name', 'phone', 'date_of_birth']));

            $tutorProfile = $user->tutorProfile;

            if (! $tutorProfile) {
                return;
            }

            $data = $request->safe()->only(['display_name', 'bio']);

            if ($request->hasFile('profile_photo')) {
                if ($tutorProfile->profile_photo) {
                    Storage::disk('public')->delete($tutorProfile->profile_photo);
                }

                $data['profile_photo'] = $request->file('profile_photo')->store('profile-photos', 'public');
            }

            if ($data) {
                $tutorProfile->update($data);
            }
        });

        return response()->json([
            'user' => new UserResource($user->fresh(['tutorProfile', 'studentGuardian'])),
        ]);
    }

    /**
     * Change the signed-in user's password. Every other session (API token)
     * is revoked so a compromised device is signed out; this one stays.
     */
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        $user->update(['password' => $request->validated('password')]);

        $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();

        return response()->json(['message' => 'Your password has been updated.']);
    }
}
