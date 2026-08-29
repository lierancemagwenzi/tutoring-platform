<?php

namespace App\Services\Commerce;

use App\Enums\Currency;
use App\Enums\OrderStatus;
use App\Enums\ProductType;
use App\Models\Booking;
use App\Models\Order;
use App\Models\SelfPacedCourse;
use App\Models\User;
use App\Services\Marketplace\SelfPacedCoursePricingService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OrderService
{
    public function __construct(
        private readonly SelfPacedCoursePricingService $pricing,
        private readonly OrderNumberGenerator $orderNumbers,
        private readonly PaymentService $payments,
        private readonly EnrollmentService $enrollments,
        private readonly BookingFeeService $bookingFee,
    ) {}

    /**
     * Create a Pending order (+ a Pending payment, unless the course is
     * free) for a student purchasing a self-paced course. Free courses skip
     * PayFast entirely — there is nothing to charge, so the order is marked
     * Paid and the enrollment activated synchronously.
     */
    public function createForCourse(User $student, SelfPacedCourse $course): Order
    {
        if ($this->enrollments->hasAccess($student, $course)) {
            throw new RuntimeException('You already own this course.');
        }

        $pricing = $this->pricing->resolve($course);

        $currency = $course->currency ?? Currency::ZAR;

        if ($currency !== Currency::ZAR) {
            // PayFast Sandbox only settles ZAR in this phase.
            throw new RuntimeException('This course cannot be purchased in its listed currency yet.');
        }

        $originalPrice = $pricing['is_free'] ? 0.0 : (float) $pricing['original_price'];
        $finalPrice = $pricing['is_free']
            ? 0.0
            : (float) ($pricing['discounted_price'] ?? $pricing['original_price']);
        $discountAmount = $originalPrice - $finalPrice;

        return DB::transaction(function () use ($student, $course, $currency, $originalPrice, $finalPrice, $discountAmount) {
            $order = Order::create([
                'student_id' => $student->id,
                'order_number' => $this->orderNumbers->generate(),
                'status' => OrderStatus::Pending,
                'currency' => $currency,
                'total_amount' => $originalPrice,
                'discount_amount' => $discountAmount,
                'final_amount' => $finalPrice,
            ]);

            $order->items()->create([
                'product_type' => ProductType::CourseOffering->value,
                'product_id' => $course->id,
                'quantity' => 1,
                'unit_price' => $originalPrice,
                'discount' => $discountAmount,
                'total' => $finalPrice,
            ]);

            if ($finalPrice <= 0.0) {
                $order->update(['status' => OrderStatus::Paid]);
                $this->enrollments->activate($order->fresh('items'));

                return $order->fresh(['items', 'latestPayment']);
            }

            $this->payments->createPendingForOrder($order);

            return $order->fresh(['items', 'latestPayment']);
        });
    }

    /**
     * Create a Pending order (Order + OrderItem only, no Payment yet) for a
     * tutoring service booking the tutor has just accepted. Unlike
     * createForCourse(), payment isn't started here — the student may pay
     * any time after acceptance, so a Payment is only created lazily when
     * they actually choose to pay (see OrderController::pay()).
     */
    public function createForBooking(Booking $booking): Order
    {
        $currency = $booking->currency ?? Currency::ZAR;

        if ($currency !== Currency::ZAR) {
            // PayFast Sandbox only settles ZAR in this phase.
            throw new RuntimeException('This booking cannot be paid for in its listed currency yet.');
        }

        $price = (float) $booking->price;
        $fee = $this->bookingFee->calculate($price);

        return DB::transaction(function () use ($booking, $currency, $price, $fee) {
            $order = Order::create([
                'student_id' => $booking->student_id,
                'order_number' => $this->orderNumbers->generate(),
                'status' => OrderStatus::Pending,
                'currency' => $currency,
                'total_amount' => $price,
                'discount_amount' => 0,
                'booking_fee_amount' => $fee['amount'],
                'booking_fee_percentage' => $fee['percentage'],
                // The Platform & Booking Fee is charged to the student on
                // top of the tutor's price but never touches OrderItem —
                // CommissionSnapshotService computes commission/tutor payout
                // from OrderItem.total alone, so folding the fee in there
                // would silently corrupt the tutor's earnings.
                'final_amount' => $price + $fee['amount'],
            ]);

            $order->items()->create([
                'product_type' => ProductType::TutoringServiceBooking->value,
                'product_id' => $booking->id,
                'quantity' => 1,
                'unit_price' => $price,
                'discount' => 0,
                'total' => $price,
            ]);

            return $order->fresh(['items', 'latestPayment']);
        });
    }
}
