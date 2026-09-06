<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Admin\PlatformSettingService;
use Illuminate\Http\JsonResponse;

class LegalDocumentController extends Controller
{
    /**
     * Public, unauthenticated read of the admin-authored Terms & Conditions
     * and Privacy Policy — needed by the registration wizard (which shows
     * these before an account exists) as well as anyone else linking to them.
     */
    public function __invoke(PlatformSettingService $settings): JsonResponse
    {
        return response()->json([
            'terms_and_conditions' => $settings->get('legal.terms_and_conditions', ''),
            'privacy_policy' => $settings->get('legal.privacy_policy', ''),
        ]);
    }
}
