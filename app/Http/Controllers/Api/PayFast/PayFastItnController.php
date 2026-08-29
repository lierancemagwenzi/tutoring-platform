<?php

namespace App\Http\Controllers\Api\PayFast;

use App\Http\Controllers\Controller;
use App\Services\Commerce\PayFast\PayFastItnHandler;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

class PayFastItnController extends Controller
{
    /**
     * Receive PayFast's Instant Transaction Notification. PayFast's server
     * posts here directly (no Sanctum token, no browser involved) and only
     * understands an HTTP status code — never render a JSON error body, and
     * never let an exception escape as a 500, or PayFast will keep retrying
     * a request that can never succeed.
     */
    public function handle(Request $request, PayFastItnHandler $handler): Response
    {
        try {
            $handler->handle($request->all());
        } catch (Throwable $exception) {
            Log::error('PayFast ITN handling threw an exception.', [
                'message' => $exception->getMessage(),
                'payload' => $request->all(),
            ]);
        }

        return response('', 200);
    }
}
