<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FinancialRuleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'scope' => $this->scope->value,
            'target' => match ($this->scope->value) {
                'tutor' => $this->tutorProfile ? ['id' => $this->tutorProfile->id, 'name' => $this->tutorProfile->display_name] : null,
                'service' => $this->service ? ['id' => $this->service->id, 'name' => $this->service->title] : null,
                'course' => $this->selfPacedCourse ? ['id' => $this->selfPacedCourse->id, 'name' => $this->selfPacedCourse->title] : null,
                default => null,
            },
            // Passed through as their native decimal:2 string representation
            // (e.g. "62.35"), not cast to float — casting a decimal string to
            // PHP float introduces binary floating-point noise for amounts
            // that aren't exactly representable (e.g. 62.35 becomes
            // 62.35000000000000142...) once JSON-encoded. Matches the
            // existing convention in PaymentManagementResource /
            // TutoringServiceManagementResource.
            'percentage' => $this->percentage,
            'fixed_fee' => $this->fixed_fee,
            'provider_fee_percentage' => $this->provider_fee_percentage,
            'provider_fee_fixed' => $this->provider_fee_fixed,
            'currency' => $this->currency,
            'is_active' => $this->is_active,
            'effective_from' => $this->effective_from?->toIso8601String(),
            'is_scheduled' => $this->effective_from?->isFuture() ?? false,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
