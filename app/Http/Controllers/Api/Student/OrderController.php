<?php

namespace App\Http\Controllers\Api\Student;

use App\Enums\OrderStatus;
use App\Enums\PaymentTransactionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\CreateOrderRequest;
use App\Http\Resources\Commerce\OrderResource;
use App\Models\Booking;
use App\Models\Order;
use App\Models\SelfPacedCourse;
use App\Services\Commerce\OrderService;
use App\Services\Commerce\PayFast\PayFastGatewayService;
use App\Services\Commerce\PaymentService;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class OrderController extends Controller
{
    /**
     * The relations eager-loaded on every order response. `items.product`
     * uses morphWith so a booking's `service` (needed by OrderItemResource's
     * product summary) loads alongside a course's product with no N+1 —
     * each morphed type only gets the extra relations it actually needs.
     *
     * @return array<int|string, mixed>
     */
    private static function relations(): array
    {
        return [
            'items.product' => fn (MorphTo $morphTo) => $morphTo->morphWith([
                Booking::class => ['service'],
            ]),
            'latestPayment',
        ];
    }

    /**
     * The logged in student's purchase history.
     */
    public function index(Request $request): JsonResponse
    {
        $orders = $request->user()->orders()
            ->with(self::relations())
            ->latest()
            ->paginate(12);

        return response()->json([
            'orders' => OrderResource::collection($orders->items()),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    /**
     * Create a pending order (and, unless the course is free, a pending
     * payment) for a self-paced course purchase.
     */
    public function store(CreateOrderRequest $request, OrderService $orders): JsonResponse
    {
        $course = SelfPacedCourse::findOrFail($request->validated('course_offering_id'));

        try {
            $order = $orders->createForCourse($request->user(), $course);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'order' => new OrderResource($order->load(self::relations())),
        ], 201);
    }

    /**
     * Show a single order belonging to the logged in student.
     */
    public function show(Request $request, Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        return response()->json([
            'order' => new OrderResource($order->load(self::relations())),
        ]);
    }

    /**
     * The PayFast redirect payload for a pending order — the frontend
     * auto-submits these fields as a real browser POST to PayFast's hosted
     * page. A course order already has a Pending payment from checkout; a
     * booking order doesn't get one until the student first calls this
     * (payment is deferred until they choose to pay), so a Payment is
     * created lazily here if none exists yet, or if the last attempt
     * Failed/was Cancelled — reusing the same Order rather than creating a
     * new one on retry. A free/already-paid order has nothing to pay.
     */
    public function pay(Request $request, Order $order, PayFastGatewayService $gateway, PaymentService $payments): JsonResponse
    {
        $this->authorize('view', $order);

        if ($order->status !== OrderStatus::Pending) {
            return response()->json(['message' => 'This order has no outstanding payment.'], 422);
        }

        $order->load('latestPayment');
        $payment = $order->latestPayment;

        if (! $payment || in_array($payment->status, [PaymentTransactionStatus::Failed, PaymentTransactionStatus::Cancelled], true)) {
            $payment = $payments->createPendingForOrder($order);
        }

        return response()->json([
            'process_url' => $gateway->processUrl(),
            'fields' => $gateway->buildRedirectPayload($order, $payment),
        ]);
    }
}
