<?php

namespace App\Http\Controllers\Api\Tutor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tutor\StorePaymentTicketCommentRequest;
use App\Http\Requests\Tutor\StorePaymentTicketRequest;
use App\Http\Resources\Tutor\PaymentTicketCommentResource;
use App\Http\Resources\Tutor\PaymentTicketResource;
use App\Models\FinancialTransaction;
use App\Models\PaymentTicket;
use App\Services\Commerce\PaymentTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentTicketController extends Controller
{
    public function __construct(private readonly PaymentTicketService $tickets) {}

    public function store(StorePaymentTicketRequest $request, FinancialTransaction $financialTransaction): JsonResponse
    {
        abort_unless($financialTransaction->tutor_profile_id === $request->user()->tutorProfile?->id, 403);

        $ticket = $this->tickets->raise($financialTransaction, $request->user()->tutorProfile, $request->validated('message'));

        return response()->json(['ticket' => new PaymentTicketResource($ticket->load('financialTransaction'))], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $tickets = $request->user()->tutorProfile
            ->paymentTickets()
            ->with('financialTransaction')
            ->withCount('comments')
            ->when($request->string('status')->isNotEmpty(), fn ($query) => $query->where('status', $request->string('status')))
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

    public function show(Request $request, PaymentTicket $paymentTicket): JsonResponse
    {
        abort_unless($paymentTicket->tutor_profile_id === $request->user()->tutorProfile?->id, 403);

        return response()->json([
            'ticket' => new PaymentTicketResource($paymentTicket->load('financialTransaction')),
            'comments' => PaymentTicketCommentResource::collection($paymentTicket->comments()->with('author')->get()),
        ]);
    }

    public function storeComment(StorePaymentTicketCommentRequest $request, PaymentTicket $paymentTicket): JsonResponse
    {
        abort_unless($paymentTicket->tutor_profile_id === $request->user()->tutorProfile?->id, 403);

        $comment = $this->tickets->addComment($paymentTicket, $request->user(), $request->validated('body'));

        return response()->json(['comment' => new PaymentTicketCommentResource($comment->load('author'))], 201);
    }
}
