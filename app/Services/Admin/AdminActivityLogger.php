<?php

namespace App\Services\Admin;

use App\Models\AdminActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Records administrative actions for later auditing. This is the
 * foundation only — no browsing UI exists yet (see the Admin module plan),
 * just a durable, append-only trail every state-changing admin action
 * writes to.
 */
class AdminActivityLogger
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function log(User $actor, string $action, Model $subject, ?string $description = null, array $metadata = []): void
    {
        AdminActivityLog::create([
            'actor_id' => $actor->id,
            'action' => $action,
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'description' => $description,
            'metadata' => $metadata,
        ]);
    }
}
