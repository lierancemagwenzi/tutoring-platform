<?php

namespace App\Services\Commerce\PayFast;

use App\Enums\ProductType;
use App\Models\Booking;
use App\Models\Order;
use App\Models\Payment;

/**
 * Builds the field payload for the auto-submitting redirect form to
 * PayFast's hosted payment page. PayFast-specific concerns (field names,
 * URL shape, signing) live entirely in this namespace — the rest of the
 * commerce layer never needs to know about them.
 */
class PayFastGatewayService
{
    public function __construct(private readonly PayFastSignature $signature) {}

    /**
     * @return array<string, string>
     */
    public function buildRedirectPayload(Order $order, Payment $payment): array
    {
        $order->loadMissing('student', 'items.product');
        $item = $order->items->first();
        $student = $order->student;

        $fields = [
            'merchant_id' => (string) config('services.payfast.merchant_id'),
            'merchant_key' => (string) config('services.payfast.merchant_key'),
            'return_url' => $this->frontendUrl("/student/orders/{$order->id}/complete"),
            'cancel_url' => $this->frontendUrl("/student/orders/{$order->id}/complete"),
            'notify_url' => rtrim((string) config('app.url'), '/').'/api/payfast/itn',
            'name_first' => $student->first_name,
            'name_last' => $student->last_name,
            'email_address' => $student->email,
            'm_payment_id' => $payment->payment_reference,
            'amount' => number_format((float) $payment->amount, 2, '.', ''),
            'item_name' => $this->itemName($item, $order),
            'custom_str1' => (string) $order->id,
        ];

        $fields['signature'] = $this->signature->generate($fields, config('services.payfast.passphrase'));

        return $fields;
    }

    private function itemName(?object $item, Order $order): string
    {
        $product = $item?->product;

        if (! $product) {
            return $order->order_number;
        }

        if ($item->product_type === ProductType::TutoringServiceBooking->value && $product instanceof Booking) {
            return $product->service?->title ?? $order->order_number;
        }

        return $product->title ?? $order->order_number;
    }

    public function processUrl(): string
    {
        return (string) config('services.payfast.process_url');
    }

    private function frontendUrl(string $path): string
    {
        return rtrim((string) config('app.url'), '/').$path;
    }
}
