<?php

namespace App\Services\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Cross-role user directory (students, tutors, administrators) — search,
 * filter, and account enable/disable. Distinct from TutorApprovalService,
 * which handles the tutor-specific application review workflow. Admin-role
 * accounts are deliberately out of scope for disable()/enable() — see
 * AdminAccountService, which is the super-admin-only equivalent for
 * managing other administrators.
 */
class AdminUserManagementService
{
    public function __construct(private readonly AdminActivityLogger $logger) {}

    /**
     * @param  array{search?: string, role?: string, verified?: bool}  $filters
     */
    public function list(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = User::query();

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(fn ($q) => $q->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"));
        }

        if (! empty($filters['role'])) {
            $query->where('role', $filters['role']);
        }

        if (array_key_exists('verified', $filters) && $filters['verified'] !== null) {
            $filters['verified'] ? $query->whereNotNull('email_verified_at') : $query->whereNull('email_verified_at');
        }

        return $query->latest()->paginate($perPage);
    }

    public function disable(User $user, User $actor): User
    {
        abort_if($user->role === UserRole::Admin, 422, 'Manage administrator accounts from the Admins page.');

        $user->update(['disabled_at' => now()]);
        $user->tokens()->delete();

        $this->logger->log($actor, 'user.disabled', $user, "Disabled account for {$user->email}.");

        return $user;
    }

    public function enable(User $user, User $actor): User
    {
        abort_if($user->role === UserRole::Admin, 422, 'Manage administrator accounts from the Admins page.');

        $user->update(['disabled_at' => null]);

        $this->logger->log($actor, 'user.enabled', $user, "Enabled account for {$user->email}.");

        return $user;
    }
}
