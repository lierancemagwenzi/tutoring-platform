<?php

namespace App\Services\Admin;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * A lightweight health check — never exposes exception details or
 * infrastructure internals to the client, only healthy/warning/failed per
 * check (real failures are logged server-side for the admin to investigate
 * via normal log tooling).
 */
class SystemHealthService
{
    public function __construct(private readonly IntegrationStatusService $integrations) {}

    /**
     * @return array<string, array{status: string, message: string}>
     */
    public function check(): array
    {
        return [
            'database' => $this->databaseHealth(),
            'email' => $this->configHealth($this->integrations->emailConfigured(), 'Email sending is configured.', 'Email is not configured.'),
            'payments' => $this->configHealth($this->integrations->payfastConfigured(), 'PayFast is configured.', 'PayFast is not configured.'),
            'h5p' => $this->configHealth($this->integrations->h5pConfigured(), 'H5P server is configured.', 'H5P is not configured.'),
            'certificates' => $this->storageHealth(),
            'storage' => $this->storageHealth(),
            'queue' => $this->configHealth(filled(config('queue.default')), 'Queue driver is configured.', 'No queue driver configured.'),
        ];
    }

    /**
     * @return array{status: string, message: string}
     */
    private function databaseHealth(): array
    {
        try {
            DB::connection()->getPdo();

            return ['status' => 'healthy', 'message' => 'Database connection is healthy.'];
        } catch (Throwable $e) {
            Log::error('Admin system health: database check failed.', ['exception' => $e]);

            return ['status' => 'failed', 'message' => 'Database connection failed.'];
        }
    }

    /**
     * @return array{status: string, message: string}
     */
    private function storageHealth(): array
    {
        try {
            $path = 'health-check/'.Str::random(10).'.txt';
            Storage::disk('local')->put($path, 'ok');
            Storage::disk('local')->delete($path);

            return ['status' => 'healthy', 'message' => 'Storage is writable.'];
        } catch (Throwable $e) {
            Log::error('Admin system health: storage check failed.', ['exception' => $e]);

            return ['status' => 'failed', 'message' => 'Storage is not writable.'];
        }
    }

    /**
     * @return array{status: string, message: string}
     */
    private function configHealth(bool $configured, string $healthyMessage, string $warningMessage): array
    {
        return $configured
            ? ['status' => 'healthy', 'message' => $healthyMessage]
            : ['status' => 'warning', 'message' => $warningMessage];
    }
}
