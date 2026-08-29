<?php

namespace App\Services\Commerce;

use App\Models\Order;
use Illuminate\Support\Str;

class OrderNumberGenerator
{
    /**
     * A human-readable, unique order number — not used for any lookup logic,
     * purely a display/reference value for students and support.
     */
    public function generate(): string
    {
        do {
            $candidate = 'ORD-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
        } while (Order::query()->where('order_number', $candidate)->exists());

        return $candidate;
    }
}
