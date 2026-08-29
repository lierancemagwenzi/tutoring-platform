<?php

namespace App\Http\Resources;

use App\Enums\UserRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupportTicketCommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'author' => [
                'id' => $this->author->id,
                'name' => trim("{$this->author->first_name} {$this->author->last_name}"),
                'is_admin' => $this->author->role === UserRole::Admin,
            ],
            'body' => $this->body,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
