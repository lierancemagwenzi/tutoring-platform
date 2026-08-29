<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Provisions a platform administrator. There is no public admin
 * registration endpoint by design — admin accounts are created
 * deliberately via this command, not self-service.
 */
class CreateAdminUser extends Command
{
    protected $signature = 'admin:create {email} {first-name} {last-name} {--password=}';

    protected $description = 'Create a platform administrator account.';

    public function handle(): int
    {
        $email = $this->argument('email');

        if (User::where('email', $email)->exists()) {
            $this->error("A user with email \"{$email}\" already exists.");

            return self::FAILURE;
        }

        $password = $this->option('password') ?? Str::random(16);

        $admin = User::create([
            'first_name' => $this->argument('first-name'),
            'last_name' => $this->argument('last-name'),
            'email' => $email,
            'password' => Hash::make($password),
            'role' => UserRole::Admin,
            'status' => UserStatus::Approved,
        ]);

        // email_verified_at isn't mass-assignable (not in $fillable), so it
        // has to be set as a separate step rather than passed to create().
        // This command remains the only way to create the very first admin
        // on a fresh install, so it always provisions a super admin — every
        // other admin account comes from a super admin's invite thereafter.
        $admin->forceFill(['email_verified_at' => now(), 'is_super_admin' => true])->save();

        $this->info("Admin account created for {$email}.");

        if (! $this->option('password')) {
            $this->line("Generated password: {$password}");
        }

        return self::SUCCESS;
    }
}
