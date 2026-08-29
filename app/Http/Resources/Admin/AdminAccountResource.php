<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminAccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => trim("{$this->first_name} {$this->last_name}"),
            'email' => $this->email,
            'is_super_admin' => $this->is_super_admin,
            'is_pending' => $this->email_verified_at === null,
            'disabled' => $this->disabled_at !== null,
            'invited_at' => $this->created_at->toIso8601String(),
        ];
    }
}
