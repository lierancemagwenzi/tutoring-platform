<?php

namespace App\Http\Resources\Tutor;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A tutor's own view of their banking details — full, unmasked, since it's
 * exactly what they submitted. Unlike TutorConnectedAccountResource (which
 * never exposes OAuth tokens to anyone), this data exists specifically so
 * the tutor can review/correct what the admin will use to pay them.
 */
class BankAccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'bank_name' => $this->bank_name,
            'account_holder_name' => $this->account_holder_name,
            'account_number' => $this->account_number,
            'branch_code' => $this->branch_code,
            'account_type' => $this->account_type,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
