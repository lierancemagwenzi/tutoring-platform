<?php

namespace App\Services\Commerce;

use App\Enums\PaymentProvider;
use App\Enums\PaymentTransactionStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Str;

/**
 * The only class allowed to mutate Payment::status — kept separate from the
 * PayFast-specific gateway code so a future provider never needs to touch
 * Payment directly, only call these same two transition methods.
 */
class PaymentService
{
    public function createPendingForOrder(Order $order): Payment
    {
        return Payment::create([
            'order_id' => $order->id,
            'provider' => PaymentProvider::PayFast,
            'payment_reference' => $this->generateReference(),
            'amount' => $order->final_amount,
            'currency' => $order->currency,
            'status' => PaymentTransactionStatus::Pending,
        ]);
    }

    /**
     * @param  array<string, mixed>  $rawResponse
     */
    public function markSuccessful(Payment $payment, string $providerReference, ?string $paymentMethod, array $rawResponse): Payment
    {
        $payment->update([
            'provider_reference' => $providerReference,
            'payment_method' => $paymentMethod,
            'provider_response' => $rawResponse,
            'status' => PaymentTransactionStatus::Successful,
            'paid_at' => now(),
        ]);

        return $payment->fresh();
    }

    /**
     * @param  array<string, mixed>  $rawResponse
     */
    public function markFailed(Payment $payment, array $rawResponse): Payment
    {
        $payment->update([
            'provider_response' => $rawResponse,
            'status' => PaymentTransactionStatus::Failed,
        ]);

        return $payment->fresh();
    }

    private function generateReference(): string
    {
        do {
            $candidate = 'PAY-'.now()->format('Ymd').'-'.strtoupper(Str::random(10));
        } while (Payment::query()->where('payment_reference', $candidate)->exists());

        return $candidate;
    }
}
