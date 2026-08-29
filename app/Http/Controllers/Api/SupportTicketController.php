<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSupportTicketCommentRequest;
use App\Http\Requests\StoreSupportTicketRequest;
use App\Http\Resources\SupportTicketCommentResource;
use App\Http\Resources\SupportTicketResource;
use App\Models\SupportTicket;
use App\Services\Support\SupportTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportTicketController extends Controller
{
    public function __construct(private readonly SupportTicketService $tickets) {}

    public function index(Request $request): JsonResponse
    {
        $tickets = $request->user()->supportTickets()
            ->withCount('comments')
            ->when($request->string('status')->isNotEmpty(), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()
            ->paginate((int) $request->integer('per_page', 15));

        return response()->json([
            'tickets' => SupportTicketResource::collection($tickets->items()),
            'meta' => [
                'current_page' => $tickets->currentPage(),
                'last_page' => $tickets->lastPage(),
                'per_page' => $tickets->perPage(),
                'total' => $tickets->total(),
            ],
        ]);
    }

    public function store(StoreSupportTicketRequest $request): JsonResponse
    {
        $ticket = $this->tickets->raise($request->user(), $request->validated('subject'), $request->validated('message'));

        return response()->json(['ticket' => new SupportTicketResource($ticket->load('user'))], 201);
    }

    public function show(Request $request, SupportTicket $supportTicket): JsonResponse
    {
        abort_unless($supportTicket->user_id === $request->user()->id, 403);

        return response()->json([
            'ticket' => new SupportTicketResource($supportTicket->load('user')),
            'comments' => SupportTicketCommentResource::collection($supportTicket->comments()->with('author')->get()),
        ]);
    }

    public function storeComment(StoreSupportTicketCommentRequest $request, SupportTicket $supportTicket): JsonResponse
    {
        abort_unless($supportTicket->user_id === $request->user()->id, 403);

        $comment = $this->tickets->addComment($supportTicket, $request->user(), $request->validated('body'));

        return response()->json(['comment' => new SupportTicketCommentResource($comment->load('author'))], 201);
    }
}
