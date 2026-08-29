<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\PaymentTicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePaymentTicketCommentRequest;
use App\Http\Requests\Admin\UpdatePaymentTicketStatusRequest;
use App\Http\Resources\Admin\PaymentTicketCommentResource;
use App\Http\Resources\Admin\PaymentTicketResource;
use App\Models\PaymentTicket;
use App\Services\Commerce\PaymentTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentTicketController extends Controller
{
    public function __construct(private readonly PaymentTicketService $tickets) {}

    public function index(Request $request): JsonResponse
    {
        $tickets = PaymentTicket::query()
            ->with(['tutorProfile', 'financialTransaction'])
            ->withCount('comments')
            ->when($request->string('status')->isNotEmpty(), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('tutor_profile_id'), fn ($query) => $query->where('tutor_profile_id', $request->integer('tutor_profile_id')))
            ->latest()
            ->paginate((int) $request->integer('per_page', 15));

        return response()->json([
            'tickets' => PaymentTicketResource::collection($tickets->items()),
            'meta' => [
                'current_page' => $tickets->currentPage(),
                'last_page' => $tickets->lastPage(),
                'per_page' => $tickets->perPage(),
                'total' => $tickets->total(),
            ],
        ]);
    }

    public function show(PaymentTicket $paymentTicket): JsonResponse
    {
        return response()->json([
            'ticket' => new PaymentTicketResource($paymentTicket->load(['tutorProfile', 'financialTransaction'])),
            'comments' => PaymentTicketCommentResource::collection($paymentTicket->comments()->with('author')->get()),
        ]);
    }

    public function updateStatus(UpdatePaymentTicketStatusRequest $request, PaymentTicket $paymentTicket): JsonResponse
    {
        $ticket = $this->tickets->updateStatus($paymentTicket, $request->enum('status', PaymentTicketStatus::class), $request->user());

        return response()->json(['ticket' => new PaymentTicketResource($ticket->load(['tutorProfile', 'financialTransaction']))]);
    }

    public function storeComment(StorePaymentTicketCommentRequest $request, PaymentTicket $paymentTicket): JsonResponse
    {
        $comment = $this->tickets->addComment($paymentTicket, $request->user(), $request->validated('body'));

        return response()->json(['comment' => new PaymentTicketCommentResource($comment->load('author'))], 201);
    }
}
