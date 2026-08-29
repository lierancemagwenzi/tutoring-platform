<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Mail\AdminTestEmail;
use App\Services\Admin\QuickSetupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class QuickSetupController extends Controller
{
    public function __construct(private readonly QuickSetupService $quickSetup) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'checklist' => $this->quickSetup->checklist(),
            'progress' => $this->quickSetup->progress(),
        ]);
    }

    public function sendTestEmail(Request $request): JsonResponse
    {
        try {
            Mail::to($request->user()->email)->send(new AdminTestEmail);

            return response()->json(['sent' => true, 'message' => "Test email sent to {$request->user()->email}."]);
        } catch (Throwable $e) {
            Log::error('Admin quick setup: test email failed to send.', ['exception' => $e]);

            return response()->json(['sent' => false, 'message' => 'Failed to send test email. Check your email configuration.'], 422);
        }
    }
}
