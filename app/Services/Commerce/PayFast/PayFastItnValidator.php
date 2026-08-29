<?php

namespace App\Services\Commerce\PayFast;

use Illuminate\Support\Facades\Http;

/**
 * The official PayFast server-to-server confirmation step: an ITN's
 * signature can be recomputed from data alone, but PayFast additionally
 * requires posting the raw payload back to them and checking their own
 * "VALID" response before it's trusted — defense in depth against a
 * spoofed request that happens to guess a valid-looking payload.
 */
class PayFastItnValidator
{
    /**
     * @param  array<string, mixed>  $rawPayload
     */
    public function confirmWithPayFast(array $rawPayload): bool
    {
        $response = Http::asForm()->post(config('services.payfast.validate_url'), $rawPayload);

        return $response->successful() && trim($response->body()) === 'VALID';
    }
}
