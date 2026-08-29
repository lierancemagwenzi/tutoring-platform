<?php

namespace App\Services\Admin;

use App\Enums\OtpPurpose;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Mail\AdminInviteMail;
use App\Models\User;
use App\Services\Auth\OtpService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Super-admin-only management of other administrator accounts — inviting,
 * deactivating, and deleting. The counterpart to AdminUserManagementService,
 * which deliberately excludes admin-role accounts from its disable()/
 * enable() now that this service owns that responsibility exclusively.
 */
class AdminAccountService
{
    public function __construct(
        private readonly OtpService $otp,
        private readonly AdminActivityLogger $logger,
    ) {}

    /**
     * Creates the invited admin's account immediately with an unguessable
     * placeholder password — they can't log in until they complete the
     * accept-invite flow and set a real one. A regular (non-super) admin,
     * always: only this service's caller (a super admin, enforced by route
     * middleware) can invite, and an invited admin can never invite others.
     */
    public function invite(string $firstName, string $lastName, string $email, User $actor): User
    {
        $invitee = User::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'password' => Hash::make(Str::random(40)),
            'role' => UserRole::Admin,
            'status' => UserStatus::Approved,
        ]);

        // is_super_admin isn't mass-assignable (deliberately, see the User
        // model), so it has to be set as a separate step — the DB default
        // (false) applies either way, but the in-memory model returned here
        // wouldn't reflect it without this, since Eloquent doesn't refetch
        // DB-applied defaults after create().
        $invitee->forceFill(['is_super_admin' => false])->save();

        $this->sendInvite($invitee, $actor);

        $this->logger->log($actor, 'admin.invited', $invitee, "Invited {$invitee->email} as an administrator.");

        return $invitee;
    }

    public function resendInvite(User $invitee, User $actor): void
    {
        abort_if($invitee->hasVerifiedEmail(), 422, 'This admin has already accepted their invite.');

        $this->sendInvite($invitee, $actor);
    }

    private function sendInvite(User $invitee, User $actor): void
    {
        $generated = $this->otp->generate($invitee, OtpPurpose::AdminInvite);

        Mail::to($invitee->email)->queue(new AdminInviteMail(
            firstName: $invitee->first_name,
            inviterName: trim("{$actor->first_name} {$actor->last_name}"),
            code: $generated->code,
            expiresInMinutes: 10,
        ));
    }

    public function deactivate(User $target, User $actor): User
    {
        $this->assertManageable($target, $actor);

        $target->update(['disabled_at' => now()]);
        $target->tokens()->delete();

        $this->logger->log($actor, 'admin.deactivated', $target, "Deactivated administrator account for {$target->email}.");

        return $target;
    }

    public function activate(User $target, User $actor): User
    {
        $this->assertManageable($target, $actor);

        $target->update(['disabled_at' => null]);

        $this->logger->log($actor, 'admin.activated', $target, "Activated administrator account for {$target->email}.");

        return $target;
    }

    public function delete(User $target, User $actor): void
    {
        $this->assertManageable($target, $actor);

        $this->logger->log($actor, 'admin.deleted', $target, "Deleted administrator account for {$target->email}.");

        $target->tokens()->delete();
        $target->delete();
    }

    /**
     * A super admin can never be deactivated/deleted through this service
     * (including by themselves) — only regular admins are manageable this
     * way, which also rules out accidental lockout.
     */
    private function assertManageable(User $target, User $actor): void
    {
        abort_if($target->role !== UserRole::Admin, 404);
        abort_if($target->id === $actor->id, 422, 'You cannot perform this action on your own account.');
        abort_if($target->is_super_admin, 422, 'Super administrator accounts cannot be managed this way.');
    }
}
