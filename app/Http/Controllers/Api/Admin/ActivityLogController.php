<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminActivityLogResource;
use App\Models\AdminActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    /**
     * A chronological, append-only feed of every administrative action
     * recorded via AdminActivityLogger — approvals, payouts, ticket status
     * changes, settings updates, and so on.
     */
    public function index(Request $request): JsonResponse
    {
        $logs = AdminActivityLog::query()
            ->with('actor')
            ->latest()
            ->paginate((int) $request->integer('per_page', 20));

        return response()->json([
            'logs' => AdminActivityLogResource::collection($logs->items()),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }
}
