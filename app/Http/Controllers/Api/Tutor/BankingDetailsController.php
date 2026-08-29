<?php

namespace App\Http\Controllers\Api\Tutor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tutor\StoreBankingDetailsRequest;
use App\Http\Resources\Tutor\BankAccountResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BankingDetailsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $bankAccount = $request->user()->tutorProfile->bankAccount;

        return response()->json([
            'bank_account' => $bankAccount ? new BankAccountResource($bankAccount) : null,
        ]);
    }

    public function store(StoreBankingDetailsRequest $request): JsonResponse
    {
        $tutorProfile = $request->user()->tutorProfile;

        $bankAccount = $tutorProfile->bankAccount()->updateOrCreate(
            ['tutor_profile_id' => $tutorProfile->id],
            $request->validated(),
        );

        return response()->json(['bank_account' => new BankAccountResource($bankAccount)]);
    }
}
