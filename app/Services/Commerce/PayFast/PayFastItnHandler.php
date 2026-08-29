<?php

namespace App\Services\Commerce\PayFast;

use App\Enums\OrderStatus;
use App\Enums\PaymentTransactionStatus;
use App\Models\Payment;
use App\Services\Booking\BookingConfirmationService;
use App\Services\Commerce\CommissionSnapshotService;
use App\Services\Commerce\EnrollmentService;
use App\Services\Commerce\PaymentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The full server-side ITN verification pipeline. Nothing outside this
 * class is ever allowed to mark a Payment Successful or activate an
 * Enrollment from a PayFast notification — the browser's return_url visit
 * carries zero authority, only this class, driven by PayFast's own
 * server-to-server callback, does.
 */
class PayFastItnHandler
{
    public function __construct(
        private readonly PayFastSignature $signature,
        private readonly PayFastItnValidator $validator,
        private readonly PaymentService $payments,
        private readonly EnrollmentService $enrollments,
        private readonly BookingConfirmationService $bookingConfirmation,
        private readonly CommissionSnapshotService $commissionSnapshot,
    ) {}

    /**
     * @param  array<string, mixed>  $payload  The raw, unmodified ITN POST body.
     */
    public function handle(array $payload): void
    {
        $signatureField = $payload['signature'] ?? null;
        $payloadWithoutSignature = collect($payload)->except('signature')->all();

        if (! $signatureField || ! hash_equals(
            $this->signature->generateForVerification($payloadWithoutSignature, config('services.payfast.passphrase')),
            (string) $signatureField,
        )) {
            Log::warning('PayFast ITN rejected: signature mismatch.', ['payload' => $payload]);

            return;
        }

        if (! $this->validator->confirmWithPayFast($payload)) {
            Log::warning('PayFast ITN rejected: server-side validation failed.', ['payload' => $payload]);

            return;
        }

        DB::transaction(function () use ($payload) {
            $payment = Payment::query()
                ->where('payment_reference', $payload['m_payment_id'] ?? '')
                ->lockForUpdate()
                ->first();

            if (! $payment) {
                Log::warning('PayFast ITN references an unknown payment_reference.', ['payload' => $payload]);

                return;
            }

            if ($payment->status === PaymentTransactionStatus::Successful) {
                // Duplicate ITN delivery for an already-processed payment.
                return;
            }

            $order = $payment->order;

            if ((string) ($payload['custom_str1'] ?? '') !== (string) $order->id) {
                Log::warning('PayFast ITN order/payment identity mismatch.', ['payload' => $payload, 'payment_id' => $payment->id]);
                $this->payments->markFailed($payment, $payload);

                return;
            }

            $amountMatches = number_format((float) $order->final_amount, 2, '.', '')
                === number_format((float) ($payload['amount_gross'] ?? 0), 2, '.', '');

            if (($payload['payment_status'] ?? null) !== 'COMPLETE' || ! $amountMatches) {
                Log::warning('PayFast ITN failed amount/status validation.', ['payload' => $payload, 'payment_id' => $payment->id]);
                $this->payments->markFailed($payment, $payload);

                return;
            }

            $this->payments->markSuccessful(
                $payment,
                (string) ($payload['pf_payment_id'] ?? ''),
                $payload['payment_method'] ?? null,
                $payload,
            );

            $order->update(['status' => OrderStatus::Paid]);

            $this->commissionSnapshot->record($payment);
            $this->enrollments->activate($order->fresh('items'));
            $this->bookingConfirmation->confirm($order->fresh('items'));
        });
    }
}
